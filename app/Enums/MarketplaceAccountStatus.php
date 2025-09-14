<?php

namespace App\Enums;

enum MarketplaceAccountStatus: string
{
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case PENDING = 'pending';
    case ERROR = 'error';
    case EXPIRED = 'expired';
    case LIMITED = 'limited';
    case SUSPENDED = 'suspended';
    case NOT_CONFIGURED = 'not_configured';
    
    public function label(): string
    {
        return match($this) {
            self::CONNECTED => 'Подключен',
            self::DISCONNECTED => 'Отключен',
            self::PENDING => 'В обработке',
            self::ERROR => 'Ошибка',
            self::EXPIRED => 'Токен истек',
            self::LIMITED => 'Ограниченный доступ',
            self::SUSPENDED => 'Заблокирован',
            self::NOT_CONFIGURED => 'Не настроен',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::CONNECTED => 'success',
            self::DISCONNECTED => 'warning',
            self::PENDING => 'info',
            self::ERROR => 'error',
            self::EXPIRED => 'warning',
            self::LIMITED => 'warning',
            self::SUSPENDED => 'error',
            self::NOT_CONFIGURED => 'secondary',
        };
    }
    
    public function isActive(): bool
    {
        return $this === self::CONNECTED;
    }
    
    public function isProblem(): bool
    {
        return in_array($this, [self::ERROR, self::EXPIRED, self::SUSPENDED]);
    }
}