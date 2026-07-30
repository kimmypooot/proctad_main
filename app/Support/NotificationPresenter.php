<?php

namespace App\Support;

use App\Notifications\AssignmentDeclined;
use App\Notifications\CertificateDecided;
use App\Notifications\CertificatePendingApproval;
use App\Notifications\CertificateReissued;
use App\Notifications\MemberCertificateDecided;
use App\Notifications\MemberRequirementReviewed;
use App\Notifications\VenueAssigned;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Turns a stored notification row into what the bell and the notifications
 * page both render.
 *
 * The icon and tone are derived from the notification class rather than stored
 * in `data`, so notifications already in the table get them too — a row written
 * before this existed presents exactly like one written after.
 */
final class NotificationPresenter
{
    /** @var array<class-string, array{icon: string, tone: string}> */
    private const PRESENTATION = [
        AssignmentDeclined::class => ['icon' => 'exclamation-triangle', 'tone' => 'accent'],
        CertificatePendingApproval::class => ['icon' => 'clipboard-check', 'tone' => 'amber'],
        CertificateDecided::class => ['icon' => 'document-check', 'tone' => 'brand'],
        MemberCertificateDecided::class => ['icon' => 'document-check', 'tone' => 'brand'],
        CertificateReissued::class => ['icon' => 'arrow-path', 'tone' => 'brand'],
        MemberRequirementReviewed::class => ['icon' => 'check-badge', 'tone' => 'emerald'],
        VenueAssigned::class => ['icon' => 'map-pin', 'tone' => 'brand'],
    ];

    private const FALLBACK = ['icon' => 'bell', 'tone' => 'slate'];

    /**
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        $presentation = self::PRESENTATION[$notification->type] ?? self::FALLBACK;

        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? '',
            'body' => $notification->data['body'] ?? '',
            'url' => $notification->data['url'] ?? null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at->diffForHumans(),
            'created_at_full' => $notification->created_at->format('M d, Y · g:i A'),
            // Resolved here rather than in the page, so the day headings on the
            // notifications page use the app's timezone and not the browser's.
            'date_group' => match (true) {
                $notification->created_at->isToday() => 'Today',
                $notification->created_at->isYesterday() => 'Yesterday',
                default => $notification->created_at->format('F j, Y'),
            },
            ...$presentation,
        ];
    }
}
