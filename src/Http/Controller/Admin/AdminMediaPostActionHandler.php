<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\AdminMediaPostActionUseCase;

final class AdminMediaPostActionHandler
{
    public function __construct(
        private AdminMediaPostActionUseCase $useCase
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{message:string,error:string,media_feedback:string,media_feedback_type:string}
     */
    public function handle(
        array $server,
        array $post,
        array $files,
        string $message,
        string $error,
        string $mediaFeedback,
        string $mediaFeedbackType
    ): array {
        $state = [
            'message' => $message,
            'error' => $error,
            'media_feedback' => $mediaFeedback,
            'media_feedback_type' => $mediaFeedbackType,
        ];

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $state;
        }

        return $this->useCase->handle($post, $files, $state);
    }
}
