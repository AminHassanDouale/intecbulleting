<?php

namespace App\Enums;

enum BulletinStatusEnum: string
{
    case DRAFT              = 'draft';
    case SUBMITTED          = 'submitted';
    case PEDAGOGIE_REVIEW   = 'pedagogie_review';
    case PEDAGOGIE_APPROVED = 'pedagogie_approved';
    case FINANCE_REVIEW     = 'finance_review';
    case FINANCE_APPROVED   = 'finance_approved';
    case DIRECTION_REVIEW   = 'direction_review';
    case APPROVED           = 'approved';
    case PUBLISHED          = 'published';
    case REJECTED           = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::DRAFT              => 'Brouillon',
            self::SUBMITTED          => 'Soumis',
            self::PEDAGOGIE_REVIEW   => 'En révision pédagogique',
            self::PEDAGOGIE_APPROVED => 'Approuvé — Pédagogie',
            self::FINANCE_REVIEW     => 'En révision finance',
            self::FINANCE_APPROVED   => 'Approuvé — Finance',
            self::DIRECTION_REVIEW   => 'En révision direction',
            self::APPROVED           => 'Approuvé',
            self::PUBLISHED          => 'Publié',
            self::REJECTED           => 'Rejeté',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT              => 'badge-ghost',
            self::SUBMITTED          => 'badge-info',
            self::PEDAGOGIE_REVIEW   => 'badge-warning',
            self::PEDAGOGIE_APPROVED => 'badge-success',
            self::FINANCE_REVIEW     => 'badge-warning',
            self::FINANCE_APPROVED   => 'badge-success',
            self::DIRECTION_REVIEW   => 'badge-warning',
            self::APPROVED           => 'badge-success',
            self::PUBLISHED          => 'badge-primary',
            self::REJECTED           => 'badge-error',
        };
    }

    public function nextStep(): ?self
    {
        return match($this) {
            self::DRAFT              => self::SUBMITTED,
            self::SUBMITTED          => self::PEDAGOGIE_APPROVED,   // pedagogie approves once
            self::PEDAGOGIE_REVIEW   => self::PEDAGOGIE_APPROVED,   // legacy
            self::PEDAGOGIE_APPROVED => self::FINANCE_APPROVED,     // finance approves once
            self::FINANCE_REVIEW     => self::FINANCE_APPROVED,     // legacy
            self::FINANCE_APPROVED   => self::APPROVED,             // direction approves once
            self::DIRECTION_REVIEW   => self::APPROVED,             // legacy
            self::APPROVED           => self::PUBLISHED,
            default                  => null,
        };
    }

    public function requiredRole(): ?string
    {
        return match($this) {
            self::SUBMITTED, self::PEDAGOGIE_REVIEW   => 'pedagogie',
            self::PEDAGOGIE_APPROVED, self::FINANCE_REVIEW => 'finance',
            self::FINANCE_APPROVED, self::DIRECTION_REVIEW => 'direction',
            self::APPROVED                            => 'direction',
            default                                   => null,
        };
    }
}
