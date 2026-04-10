(function () {
  // Lightbox gallery
  const overlay = document.getElementById('lightbox-overlay');
  const img = document.getElementById('lightbox-img');
  const close = document.getElementById('lightbox-close');

  if (overlay && img && close) {
    document.querySelectorAll('.lightbox-link').forEach((link) => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        img.src = this.href;
        overlay.style.display = 'flex';
      });
    });

    close.addEventListener('click', function () {
      overlay.style.display = 'none';
      img.src = '';
    });

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        overlay.style.display = 'none';
        img.src = '';
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        overlay.style.display = 'none';
        img.src = '';
      }
    });
  }

  // Certificates lightbox (images + PDF)
  const certificateModal = document.querySelector('[data-certificate-modal]');
  if (certificateModal) {
    const certificateTriggers = Array.from(document.querySelectorAll('[data-certificate-trigger="1"]'));
    const closeButtons = Array.from(certificateModal.querySelectorAll('[data-certificate-close]'));
    const prevButton = certificateModal.querySelector('[data-certificate-prev]');
    const nextButton = certificateModal.querySelector('[data-certificate-next]');
    const titleNode = certificateModal.querySelector('[data-certificate-modal-title]');
    const imageNode = certificateModal.querySelector('[data-certificate-modal-image]');
    const pdfNode = certificateModal.querySelector('[data-certificate-modal-pdf]');
    let activeIndex = -1;
    let touchStartX = 0;

    const getCertificateItem = (index) => {
      if (index < 0 || index >= certificateTriggers.length) return null;
      const trigger = certificateTriggers[index];
      return {
        title: trigger.getAttribute('data-certificate-title') || 'Certifikát',
        url: trigger.getAttribute('data-certificate-url') || trigger.getAttribute('href') || '#',
        type: trigger.getAttribute('data-certificate-type') || 'image',
      };
    };

    const renderCertificate = (index) => {
      const item = getCertificateItem(index);
      if (!item || !titleNode || !imageNode || !pdfNode) return;

      activeIndex = index;
      titleNode.textContent = item.title;

      if (item.type === 'pdf') {
        imageNode.hidden = true;
        imageNode.setAttribute('src', '');
        imageNode.setAttribute('alt', '');
        pdfNode.hidden = false;
        pdfNode.setAttribute('src', item.url);
      } else {
        pdfNode.hidden = true;
        pdfNode.setAttribute('src', 'about:blank');
        imageNode.hidden = false;
        imageNode.setAttribute('src', item.url);
        imageNode.setAttribute('alt', item.title);
      }
    };

    const openModal = (index) => {
      renderCertificate(index);
      certificateModal.hidden = false;
      document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
      certificateModal.hidden = true;
      document.body.style.overflow = '';
      if (pdfNode) pdfNode.setAttribute('src', 'about:blank');
      if (imageNode) imageNode.setAttribute('src', '');
      activeIndex = -1;
    };

    const shiftModal = (direction) => {
      if (certificateTriggers.length <= 1 || activeIndex < 0) return;
      const nextIndex = (activeIndex + direction + certificateTriggers.length) % certificateTriggers.length;
      renderCertificate(nextIndex);
    };

    certificateTriggers.forEach((trigger, index) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        openModal(index);
      });
    });

    closeButtons.forEach((button) => {
      button.addEventListener('click', () => closeModal());
    });

    if (prevButton) prevButton.addEventListener('click', () => shiftModal(-1));
    if (nextButton) nextButton.addEventListener('click', () => shiftModal(1));

    certificateModal.addEventListener('touchstart', (event) => {
      const touch = event.changedTouches && event.changedTouches[0];
      if (!touch) return;
      touchStartX = touch.clientX;
    }, { passive: true });

    certificateModal.addEventListener('touchend', (event) => {
      const touch = event.changedTouches && event.changedTouches[0];
      if (!touch) return;
      const deltaX = touch.clientX - touchStartX;
      if (Math.abs(deltaX) < 55) return;
      shiftModal(deltaX > 0 ? -1 : 1);
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
      if (certificateModal.hidden) return;
      if (event.key === 'Escape') closeModal();
      if (event.key === 'ArrowLeft') shiftModal(-1);
      if (event.key === 'ArrowRight') shiftModal(1);
    });
  }

  // Native Google reviews widget (via local backend API)
  const googleReviewsList = document.querySelector('[data-google-reviews-widget]');
  if (googleReviewsList) {
    const googleReviewsSummary = document.getElementById('google-reviews-summary');

    const escapeHtml = (value) =>
      String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const stars = (rating) => {
      const rounded = Math.max(1, Math.min(5, Number(Math.round(Number(rating || 0)))));
      return '★'.repeat(rounded) + '☆'.repeat(5 - rounded);
    };

    const initials = (name) => {
      const cleaned = String(name || '').trim();
      if (!cleaned) return 'K';
      const parts = cleaned.split(/\s+/).slice(0, 2);
      return parts.map((part) => part.charAt(0).toUpperCase()).join('');
    };

    fetch('/api/google-reviews.php', { headers: { 'X-Requested-With': 'fetch' } })
      .then((response) => response.json().then((body) => ({ ok: response.ok, body })))
      .then(({ ok, body }) => {
        if (!ok || !body || body.configured === false) {
          const message = (body && body.error) ? String(body.error) : 'Google recenze nejsou momentálně dostupné.';
          googleReviewsList.innerHTML = `<div class="reviews-empty">${escapeHtml(message)}</div>`;
          if (googleReviewsSummary) {
            googleReviewsSummary.textContent = 'Google recenze nejsou nastavené.';
          }
          return;
        }

        const summary = body.summary || {};
        const reviews = Array.isArray(body.reviews) ? body.reviews : [];
        const summaryName = String(summary.name || 'Google recenze');
        const summaryRating = Number(summary.rating || 0);
        const summaryTotal = Number(summary.total_ratings || 0);
        const summaryUrl = String(summary.url || '');
        const cacheBadge = body.stale ? ' (cache)' : '';

        if (googleReviewsSummary) {
          const summaryLabel = `${summaryRating.toFixed(1)} / 5`;
          const totalLabel = `${new Intl.NumberFormat('cs-CZ').format(summaryTotal)} hodnocení`;
          const sourceLabel = `${summaryName}${cacheBadge}`;
          const score = Math.max(1, Math.min(5, Math.round(summaryRating)));
          googleReviewsSummary.innerHTML = `
            <div class="google-summary-badge">Ověřené Google recenze</div>
            <div class="google-summary-main">
              <span class="google-summary-rating">${escapeHtml(summaryLabel)}</span>
              <span class="google-summary-stars">${escapeHtml(stars(score))}</span>
              <span class="google-summary-total">${escapeHtml(totalLabel)}</span>
            </div>
            <div class="google-summary-actions">
              ${summaryUrl ? `<a class="google-summary-link" href="${escapeHtml(summaryUrl)}" target="_blank" rel="noreferrer noopener">Zobrazit profil na Google</a>` : ''}
            </div>
            <div class="google-summary-source">${escapeHtml(sourceLabel)}</div>
          `;
        }

        if (reviews.length === 0) {
          googleReviewsList.innerHTML = '<div class="reviews-empty">Google recenze zatím nejsou k dispozici.</div>';
          return;
        }

        googleReviewsList.innerHTML = reviews.map((review) => {
          const author = escapeHtml(review.author_name || 'Klientka');
          const rawText = String(review.text || 'Bez textového hodnocení.');
          const text = escapeHtml(rawText);
          const relative = escapeHtml(review.relative_time_description || '');
          const rating = Number(review.rating || 0);
          const hasLongText = rawText.trim().length > 220;
          return `
            <article class="review-card">
              <div class="review-card-head">
                <div class="review-avatar">${escapeHtml(initials(author))}</div>
                <div class="review-author-wrap">
                  <div class="review-author">${author}</div>
                  <div class="review-time">${relative}</div>
                </div>
                <div class="review-stars" aria-label="Hodnocení ${Math.max(1, Math.min(5, Math.round(rating)))} z 5">${escapeHtml(stars(rating))}</div>
              </div>
              <p class="review-text${hasLongText ? ' is-clamped' : ''}">„${text}“</p>
              ${hasLongText ? '<button type="button" class="review-more-toggle" aria-expanded="false">Zobrazit více</button>' : ''}
            </article>
          `;
        }).join('');

        googleReviewsList.querySelectorAll('.review-more-toggle').forEach((button) => {
          button.addEventListener('click', () => {
            const card = button.closest('.review-card');
            if (!card) return;
            const textNode = card.querySelector('.review-text');
            if (!textNode) return;
            const isExpanded = textNode.classList.toggle('is-expanded');
            button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            button.textContent = isExpanded ? 'Zobrazit méně' : 'Zobrazit více';
          });
        });
      })
      .catch(() => {
        googleReviewsList.innerHTML = '<div class="reviews-empty">Google recenze se nepodařilo načíst.</div>';
        if (googleReviewsSummary) {
          googleReviewsSummary.textContent = 'Google recenze se nepodařilo načíst.';
        }
      });
  }

  // Pricing list grouped by category
  const pricingList = document.getElementById('pricing-list');
  if (pricingList) {
    const formatDuration = (minutes) => {
      const parsed = Number(minutes || 0);
      return parsed > 0 ? `${parsed} min` : 'dle služby';
    };

    const formatPrice = (price) => {
      if (price === null || price === undefined || price === '') return 'Cena na dotaz';
      const n = Number(price);
      if (Number.isNaN(n)) return 'Cena na dotaz';
      return `${new Intl.NumberFormat('cs-CZ').format(Math.round(n))} Kč`;
    };

    const escapeHtml = (value) =>
      String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    fetch('/api/services.php', { headers: { 'X-Requested-With': 'fetch' } })
      .then((response) => {
        if (!response.ok) throw new Error('Pricing unavailable');
        return response.json();
      })
      .then((payload) => {
        const services = Array.isArray(payload.services) ? payload.services : [];
        pricingList.querySelectorAll('.pricing-group, .pricing-empty').forEach((row) => row.remove());

        if (services.length === 0) {
          const empty = document.createElement('div');
          empty.className = 'pricing-empty';
          empty.textContent = 'Ceník zatím není vyplněný.';
          pricingList.appendChild(empty);
          return;
        }

        const grouped = new Map();
        services.forEach((service) => {
          const category = String(service.category || '').trim() || 'Ostatní služby';
          const categoryOrder = Number.isFinite(Number(service.category_order)) ? Number(service.category_order) : 9999;
          if (!grouped.has(category)) grouped.set(category, { order: categoryOrder, items: [] });
          const group = grouped.get(category);
          group.order = Math.min(group.order, categoryOrder);
          group.items.push(service);
        });

        const sortedGroups = Array.from(grouped.entries()).sort((a, b) => {
            const orderDiff = a[1].order - b[1].order;
            if (orderDiff !== 0) return orderDiff;
            return a[0].localeCompare(b[0], 'cs');
        });

        sortedGroups.forEach(([category, group]) => {
          const block = document.createElement('section');
          block.className = 'pricing-group';

          const header = document.createElement('div');
          header.className = 'pricing-group-header';
          header.innerHTML = `
            <div class="pricing-group-title">${escapeHtml(category)}</div>
          `;
          block.appendChild(header);

          group.items.forEach((service) => {
            const row = document.createElement('div');
            row.className = 'pricing-text-row';
            const serviceName = escapeHtml(service.name || 'Služba');
            const description = String(service.description || '').trim();
            const serviceDescription = description !== ''
              ? `<div class="pricing-service-description">${escapeHtml(description)}</div>`
              : '<div class="pricing-service-description pricing-service-description-empty"></div>';
            const bookUrl = service.id !== undefined && service.id !== null
              ? `/rezervace.php?sluzba=${encodeURIComponent(String(service.id))}`
              : '/rezervace.php';

            row.innerHTML = `
              <div class="pricing-info-cell">
                <div class="pricing-main-line">
                  <div class="pricing-service-name">${serviceName}</div>
                </div>
                <div class="pricing-details-line">
                  ${serviceDescription}
                  <div class="pricing-meta-row">
                    <span class="pricing-duration">${formatDuration(service.duration)}</span>
                    <span class="price">${formatPrice(service.price)}</span>
                  </div>
                </div>
                <div class="pricing-book-row">
                  <a class="pricing-book-link" href="${bookUrl}">Rezervovat tuto proceduru</a>
                </div>
              </div>
            `;
            block.appendChild(row);
          });

          pricingList.appendChild(block);
        });
      })
      .catch(() => {
        pricingList.querySelectorAll('.pricing-group, .pricing-empty').forEach((row) => row.remove());
        const failure = document.createElement('div');
        failure.className = 'pricing-empty';
        failure.textContent = 'Ceník se nepodařilo načíst.';
        pricingList.appendChild(failure);
      });
  }

  // Reservation form
  const form = document.querySelector('[data-reservation-form]');
  if (form) {
    const requestedServiceId = new URLSearchParams(window.location.search).get('sluzba');
    const feedbackRoot = document.querySelector('[data-reservation-feedback]');
    const successCard = document.querySelector('[data-reservation-success]');
    const successMessage = successCard ? successCard.querySelector('[data-success-message]') : null;
    const successService = successCard ? successCard.querySelector('[data-success-service]') : null;
    const successSlot = successCard ? successCard.querySelector('[data-success-slot]') : null;
    const successContact = successCard ? successCard.querySelector('[data-success-contact]') : null;
    const successResetButton = successCard ? successCard.querySelector('[data-success-reset]') : null;
    const tokenInput = form.querySelector('input[name="reservation_token"]');
    const stepElements = Array.from(form.querySelectorAll('[data-step]'));
    const stepIndicators = Array.from(form.querySelectorAll('[data-step-indicator]'));
    const stepNextButtons = Array.from(form.querySelectorAll('[data-step-next]'));
    const stepBackButtons = Array.from(form.querySelectorAll('[data-step-back]'));
    const serviceSelect = form.querySelector('[data-service-select]');
    const daySelect = form.querySelector('[data-day-select]');
    const timeSelect = form.querySelector('[data-time-select]');
    const submitButton = form.querySelector('[data-submit-button]');
    const phoneInput = form.querySelector('[data-phone-input]');
    const summaryService = form.querySelector('[data-summary-service]');
    const summarySlot = form.querySelector('[data-summary-slot]');
    const summaryContact = form.querySelector('[data-summary-contact]');
    const pickedSlotValue = form.querySelector('[data-picked-slot-value]');
    const calendarRoot = form.querySelector('[data-reservation-calendar]');
    const calendarGrid = form.querySelector('[data-calendar-grid]');
    const calendarMonth = form.querySelector('[data-calendar-month]');
    const calendarPrev = form.querySelector('[data-calendar-prev]');
    const calendarNext = form.querySelector('[data-calendar-next]');
    const timeSlots = form.querySelector('[data-time-slots]');
    const serviceMetaCategory = form.querySelector('[data-service-meta-category]');
    const serviceMetaDuration = form.querySelector('[data-service-meta-duration]');
    const serviceMetaPrice = form.querySelector('[data-service-meta-price]');
    let currentStep = 1;
    let availableDays = [];
    let availableDayMap = new Map();
    let calendarMonths = [];
    let activeCalendarMonthIndex = 0;
    let servicesById = new Map();

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

    const parseDateKey = (value) => {
      const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!match) return null;
      return {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
      };
    };

    const createDateFromKey = (value) => {
      const parts = parseDateKey(value);
      if (!parts) return null;
      return new Date(parts.year, parts.month - 1, parts.day);
    };

    const setOptions = (select, items, placeholder, selected = '') => {
      select.innerHTML = '';
      const first = document.createElement('option');
      first.value = '';
      first.textContent = placeholder;
      select.appendChild(first);

      items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.label;
        if (item.value === selected) option.selected = true;
        select.appendChild(option);
      });

      select.disabled = items.length === 0;
    };

    const fetchJson = async (url) => {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
      if (!res.ok) throw new Error('Chyba načtení');
      return res.json();
    };

    const updateReservationToken = (value) => {
      if (tokenInput && String(value || '').trim()) {
        tokenInput.value = String(value).trim();
      }
    };

    const renderFeedback = (type, message) => {
      if (!feedbackRoot) return;
      if (!message) {
        feedbackRoot.innerHTML = '';
        return;
      }

      const safeMessage = escapeHtml(message);
      feedbackRoot.innerHTML = `<div class="reservation-alert reservation-alert-${type}">${safeMessage}</div>`;
    };

    const showSuccessCard = (payload) => {
      if (!successCard) return;
      if (successMessage) {
        successMessage.textContent = String(payload?.message || 'Rezervace byla odeslaná. Potvrzení vám během chvíle dorazí e-mailem.');
      }
      if (successService) {
        successService.textContent = String(payload?.reservation?.service || selectedOptionText(serviceSelect) || '—');
      }
      if (successSlot) {
        successSlot.textContent = String(payload?.reservation?.slot || summarySlot?.textContent || '—');
      }
      if (successContact) {
        successContact.textContent = String(payload?.reservation?.contact || summaryContact?.textContent || '—');
      }

      renderFeedback('', '');
      form.hidden = true;
      successCard.hidden = false;
      successCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const resetReservationFlow = async () => {
      form.reset();
      showStep(1);
      renderFeedback('', '');
      if (successCard) successCard.hidden = true;
      form.hidden = false;
      servicesById = new Map();
      availableDays = [];
      availableDayMap = new Map();
      calendarMonths = [];
      activeCalendarMonthIndex = 0;
      if (calendarRoot && calendarGrid && calendarMonth) {
        calendarMonth.textContent = 'Vyberte službu';
        calendarGrid.innerHTML = '<div class="reservation-calendar-empty">Nejprve vyberte službu.</div>';
      }
      if (timeSlots) {
        timeSlots.innerHTML = '<div class="reservation-calendar-empty">Nejprve vyberte den.</div>';
      }
      setOptions(daySelect, [], 'Nejprve vyberte službu');
      setOptions(timeSelect, [], 'Nejprve vyberte den');
      updateSummary();
      await loadServices();
    };

    const showStep = (step) => {
      currentStep = step;
      stepElements.forEach((el) => {
        const isActive = Number(el.dataset.step) === step;
        el.classList.toggle('is-active', isActive);
        el.hidden = !isActive;
      });

      stepIndicators.forEach((chip) => {
        const chipStep = Number(chip.dataset.stepIndicator || 0);
        chip.classList.toggle('is-active', chipStep === step);
        chip.classList.toggle('is-complete', chipStep < step);
      });
    };

    const firstInvalidInStep = (step) => {
      const scope = form.querySelector(`[data-step="${step}"]`);
      if (!scope) return null;
      const fields = Array.from(scope.querySelectorAll('input, select, textarea'));
      return fields.find((field) => field.required && !field.checkValidity()) || null;
    };

    const selectedOptionText = (select) => {
      if (!select) return '';
      if (!String(select.value || '').trim()) return '';
      const selected = select.options[select.selectedIndex];
      return selected ? String(selected.textContent || '').trim() : '';
    };

    const formatServicePrice = (value) => {
      const number = Number(value);
      if (!Number.isFinite(number) || number <= 0) return '—';
      return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK',
        maximumFractionDigits: 0,
      }).format(number);
    };

    const updateServiceMeta = () => {
      const selectedId = String(serviceSelect?.value || '');
      const selectedService = selectedId ? servicesById.get(selectedId) : null;
      const category = String(selectedService?.category || '—');
      const durationValue = Number(selectedService?.duration || 0);
      const duration = durationValue > 0 ? `${durationValue} min` : '—';
      const price = formatServicePrice(selectedService?.price);

      if (serviceMetaCategory) serviceMetaCategory.textContent = `Kategorie: ${category}`;
      if (serviceMetaDuration) serviceMetaDuration.textContent = `Délka: ${duration}`;
      if (serviceMetaPrice) serviceMetaPrice.textContent = `Cena: ${price}`;
    };

    const updateSummary = () => {
      if (summaryService) {
        summaryService.textContent = selectedOptionText(serviceSelect) || 'Nevybráno';
      }
      updateServiceMeta();
      if (summarySlot) {
        const dayLabel = selectedOptionText(daySelect);
        const timeLabel = selectedOptionText(timeSelect);
        summarySlot.textContent = dayLabel && timeLabel ? `${dayLabel} v ${timeLabel}` : 'Nevybráno';
      }
      if (pickedSlotValue) {
        const dayLabel = selectedOptionText(daySelect);
        const timeLabel = selectedOptionText(timeSelect);
        pickedSlotValue.textContent = dayLabel && timeLabel
          ? `${dayLabel} v ${timeLabel}`
          : 'Zatím není vybraný den a čas.';
      }
      if (summaryContact) {
        const name = String((form.querySelector('input[name="jmeno"]')?.value || '')).trim();
        const email = String((form.querySelector('input[name="email"]')?.value || '')).trim();
        const phone = String((form.querySelector('input[name="telefon"]')?.value || '')).trim();
        const parts = [name, email, phone].filter(Boolean);
        summaryContact.textContent = parts.length ? parts.join(' • ') : 'Nevyplněno';
      }
    };

    const renderCalendar = () => {
      if (!calendarRoot || !calendarGrid || !calendarMonth) return;

      if (availableDays.length === 0 || calendarMonths.length === 0) {
        calendarMonth.textContent = 'Bez dostupných termínů';
        calendarGrid.innerHTML = '<div class="reservation-calendar-empty">Pro tuto službu zatím nejsou volné termíny.</div>';
        if (calendarPrev) calendarPrev.disabled = true;
        if (calendarNext) calendarNext.disabled = true;
        return;
      }

      const monthKey = calendarMonths[activeCalendarMonthIndex];
      const [yearRaw, monthRaw] = monthKey.split('-');
      const year = Number(yearRaw);
      const month = Number(monthRaw);

      const firstDay = new Date(year, month - 1, 1);
      const daysInMonth = new Date(year, month, 0).getDate();
      const mondayBasedWeekday = ((firstDay.getDay() + 6) % 7) + 1;
      const monthLabel = new Intl.DateTimeFormat('cs-CZ', { month: 'long', year: 'numeric' }).format(firstDay);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const selectedDay = String(daySelect?.value || '');

      calendarMonth.textContent = monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1);
      if (calendarPrev) calendarPrev.disabled = activeCalendarMonthIndex <= 0;
      if (calendarNext) calendarNext.disabled = activeCalendarMonthIndex >= calendarMonths.length - 1;

      const cells = [];
      for (let i = 1; i < mondayBasedWeekday; i += 1) {
        cells.push('<span class="reservation-calendar-spacer" aria-hidden="true"></span>');
      }

      for (let day = 1; day <= daysInMonth; day += 1) {
        const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const thisDate = createDateFromKey(dateKey);
        const isAvailable = availableDayMap.has(dateKey);
        const isPast = thisDate ? thisDate.getTime() < today.getTime() : false;
        const isSelectable = isAvailable && !isPast;
        const isSelected = selectedDay === dateKey;
        const label = availableDayMap.get(dateKey) || dateKey;

        cells.push(`
          <button
            type="button"
            class="reservation-calendar-day${isSelected ? ' is-selected' : ''}${!isSelectable ? ' is-disabled' : ''}"
            data-calendar-date="${dateKey}"
            ${isSelectable ? '' : 'disabled'}
            aria-label="${label}"
          >
            ${day}
          </button>
        `);
      }

      calendarGrid.innerHTML = cells.join('');

      calendarGrid.querySelectorAll('[data-calendar-date]').forEach((button) => {
        button.addEventListener('click', () => {
          const dateValue = String(button.getAttribute('data-calendar-date') || '');
          if (!dateValue || !daySelect) return;
          daySelect.value = dateValue;
          renderCalendar();
          loadTimes(serviceSelect.value, dateValue);
          updateSummary();
        });
      });
    };

    const updateCalendarDays = (days) => {
      availableDays = Array.isArray(days) ? days : [];
      availableDayMap = new Map();
      availableDays.forEach((day) => {
        const value = String(day?.value || '');
        if (!value) return;
        availableDayMap.set(value, String(day?.label || value));
      });

      calendarMonths = Array.from(
        new Set(
          availableDays
            .map((day) => String(day?.value || '').slice(0, 7))
            .filter((key) => /^\d{4}-\d{2}$/.test(key))
        )
      ).sort();

      const selectedMonthKey = String(daySelect?.value || '').slice(0, 7);
      const selectedIndex = calendarMonths.indexOf(selectedMonthKey);
      activeCalendarMonthIndex = selectedIndex >= 0 ? selectedIndex : 0;
      renderCalendar();
    };

    const renderTimeSlots = (items, selectedValue = '') => {
      if (!timeSlots || !timeSelect) return;

      const slots = Array.isArray(items) ? items : [];
      if (slots.length === 0) {
        timeSlots.innerHTML = '<div class="reservation-calendar-empty">Pro tento den nejsou volné časy.</div>';
        return;
      }

      timeSlots.innerHTML = slots.map((slot) => {
        const value = String(slot?.value || '');
        const label = String(slot?.label || value);
        const isSelected = value !== '' && value === selectedValue;
        return `
          <button
            type="button"
            class="reservation-time-chip${isSelected ? ' is-selected' : ''}"
            data-time-value="${value}"
            aria-pressed="${isSelected ? 'true' : 'false'}"
          >${label}</button>
        `;
      }).join('');

      timeSlots.querySelectorAll('[data-time-value]').forEach((button) => {
        button.addEventListener('click', () => {
          const value = String(button.getAttribute('data-time-value') || '');
          if (!value) return;
          timeSelect.value = value;
          renderTimeSlots(slots, value);
          updateSummary();
        });
      });
    };

    const loadTimes = async (serviceId, day, autoPickFirst = false) => {
      setOptions(timeSelect, [], 'Načítání časů…');
      if (!day) {
        setOptions(timeSelect, [], 'Nejprve vyberte den');
        if (timeSlots) {
          timeSlots.innerHTML = '<div class="reservation-calendar-empty">Nejprve vyberte den.</div>';
        }
        updateSummary();
        return;
      }

      try {
        const payload = await fetchJson(`/api/availability.php?service_id=${encodeURIComponent(serviceId)}&date=${encodeURIComponent(day)}`);
        const times = Array.isArray(payload.times) ? payload.times : [];
        setOptions(timeSelect, times, times.length ? 'Vyberte čas' : 'Pro tento den nejsou časy');
        if (autoPickFirst && times.length > 0) {
          timeSelect.value = times[0].value;
        }
        renderTimeSlots(times, String(timeSelect.value || ''));
      } catch (e) {
        setOptions(timeSelect, [], 'Časy se nepodařilo načíst');
        if (timeSlots) {
          timeSlots.innerHTML = '<div class="reservation-calendar-empty">Časy se nepodařilo načíst.</div>';
        }
      }
      updateSummary();
    };

    const loadDays = async (serviceId, autoPickFirst = false) => {
      setOptions(daySelect, [], 'Načítání dnů…');
      setOptions(timeSelect, [], 'Nejprve vyberte den');

      if (!serviceId) {
        setOptions(daySelect, [], 'Nejprve vyberte službu');
        updateCalendarDays([]);
        updateSummary();
        return;
      }

      try {
        const payload = await fetchJson(`/api/availability.php?service_id=${encodeURIComponent(serviceId)}`);
        const days = Array.isArray(payload.days) ? payload.days : [];
        setOptions(daySelect, days, days.length ? 'Vyberte den' : 'Pro tuto službu nejsou termíny');
        updateCalendarDays(days);
        if (autoPickFirst && days.length > 0) {
          daySelect.value = days[0].value;
          updateCalendarDays(days);
          await loadTimes(serviceId, days[0].value, true);
        }
      } catch (e) {
        setOptions(daySelect, [], 'Dny se nepodařilo načíst');
        updateCalendarDays([]);
      }
      updateSummary();
    };

    const loadServices = async () => {
      try {
        const payload = await fetchJson('/api/services.php');
        const services = Array.isArray(payload.services) ? payload.services : [];
        servicesById = new Map(services.map((service) => [String(service.id), service]));
        setOptions(
          serviceSelect,
          services.map((s) => ({ value: String(s.id), label: s.label || s.name || 'Služba' })),
          services.length ? 'Vyberte službu' : 'Služby nejsou dostupné'
        );

        if (services.length > 0) {
          const firstServiceId = String(services[0].id);
          const selectedServiceId = services.some((s) => String(s.id) === requestedServiceId)
            ? String(requestedServiceId)
            : firstServiceId;

          serviceSelect.value = selectedServiceId;
          await loadDays(selectedServiceId, false);
          if (requestedServiceId) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      } catch (e) {
        setOptions(serviceSelect, [], 'Služby se nepodařilo načíst');
      }
      updateSummary();
    };

    serviceSelect.addEventListener('change', () => {
      loadDays(serviceSelect.value);
      updateSummary();
      updateServiceMeta();
    });
    daySelect.addEventListener('change', () => {
      loadTimes(serviceSelect.value, daySelect.value);
      updateCalendarDays(availableDays);
      updateSummary();
    });
    timeSelect.addEventListener('change', updateSummary);

    form.querySelectorAll('input[name="jmeno"], input[name="email"], input[name="telefon"]').forEach((field) => {
      field.addEventListener('input', updateSummary);
      field.addEventListener('blur', updateSummary);
    });

    if (phoneInput) {
      phoneInput.addEventListener('input', () => {
        const raw = String(phoneInput.value || '');
        const digits = raw.replace(/\D/g, '');
        const stripped = digits.startsWith('420') ? digits.slice(3) : digits;
        const limited = stripped.slice(0, 9);
        const groups = limited.match(/.{1,3}/g) || [];
        phoneInput.value = groups.length ? `+420 ${groups.join(' ')}` : '';
        updateSummary();
      });
    }

    if (calendarPrev) {
      calendarPrev.addEventListener('click', () => {
        if (activeCalendarMonthIndex > 0) {
          activeCalendarMonthIndex -= 1;
          renderCalendar();
        }
      });
    }

    if (calendarNext) {
      calendarNext.addEventListener('click', () => {
        if (activeCalendarMonthIndex < calendarMonths.length - 1) {
          activeCalendarMonthIndex += 1;
          renderCalendar();
        }
      });
    }

    stepNextButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const invalidField = firstInvalidInStep(currentStep);
        if (invalidField) {
          invalidField.reportValidity();
          invalidField.focus();
          return;
        }
        if (currentStep < 3) {
          showStep(currentStep + 1);
          updateSummary();
        }
      });
    });

    stepBackButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (currentStep > 1) showStep(currentStep - 1);
      });
    });

    form.addEventListener('submit', (event) => {
      if (currentStep < 3) {
        event.preventDefault();
        const invalidInCurrent = firstInvalidInStep(currentStep);
        if (invalidInCurrent) {
          invalidInCurrent.reportValidity();
          invalidInCurrent.focus();
          return;
        }
        showStep(currentStep + 1);
        updateSummary();
        return;
      }

      const requiredFields = Array.from(form.querySelectorAll('[required]'));
      const invalid = requiredFields.find((field) => !field.checkValidity());
      if (invalid) {
        event.preventDefault();
        invalid.reportValidity();
        invalid.focus();
        return;
      }
      event.preventDefault();
      if (!submitButton) return;
      submitButton.disabled = true;
      submitButton.textContent = submitButton.dataset.loadingLabel || 'Odesílám...';

      const formData = new FormData(form);
      fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'fetch',
          Accept: 'application/json',
        },
      })
        .then(async (response) => {
          let payload = null;
          try {
            payload = await response.json();
          } catch (error) {
            payload = null;
          }

          if (payload?.new_token) {
            updateReservationToken(payload.new_token);
          }

          if (!response.ok || !payload || payload.success !== true) {
            throw new Error(payload?.message || 'Rezervaci se nepodařilo odeslat. Zkuste to prosím znovu.');
          }

          showSuccessCard(payload);
        })
        .catch((error) => {
          renderFeedback('error', error.message || 'Rezervaci se nepodařilo odeslat. Zkuste to prosím znovu.');
        })
        .finally(() => {
          submitButton.disabled = false;
          submitButton.textContent = submitButton.dataset.defaultLabel || 'Odeslat rezervaci';
        });
    });

    if (successResetButton) {
      successResetButton.addEventListener('click', () => {
        resetReservationFlow();
      });
    }

    showStep(1);
    updateSummary();
    updateCalendarDays([]);
    if (timeSlots) {
      timeSlots.innerHTML = '<div class="reservation-calendar-empty">Nejprve vyberte den.</div>';
    }
    loadServices();
  }

  // Mobile nav toggle
  const menuToggle = document.querySelector('.menu-toggle');
  const navLinks = document.querySelector('.nav-links');
  if (menuToggle && navLinks) {
    menuToggle.addEventListener('click', () => {
      navLinks.classList.toggle('active');
    });
  }

  // Obfuscated emails (kept for compatibility)
  document.querySelectorAll('.email').forEach((el) => {
    const user = el.getAttribute('data-user');
    const domain = el.getAttribute('data-domain');
    if (!user || !domain) return;
    const email = `${user}@${domain}`;
    el.innerHTML = `<a href="mailto:${email}">${email}</a>`;
  });
})();
