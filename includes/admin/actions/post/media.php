<?php

use PPStudio\Service\AdminMediaModule;

$mediaPostResult = (new AdminMediaModule($connection, dirname(__DIR__, 4)))
    ->postActionHandler()
    ->handle($_SERVER, $_POST, $_FILES, $message, $error, $mediaFeedback, $mediaFeedbackType);

$message = (string) ($mediaPostResult['message'] ?? $message);
$error = (string) ($mediaPostResult['error'] ?? $error);
$mediaFeedback = (string) ($mediaPostResult['media_feedback'] ?? $mediaFeedback);
$mediaFeedbackType = (string) ($mediaPostResult['media_feedback_type'] ?? $mediaFeedbackType);
