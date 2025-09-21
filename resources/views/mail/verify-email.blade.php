<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Подтверждение email адреса</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { background: #fff; padding: 30px; border: 1px solid #dee2e6; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; 
                color: white !important; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 20px; text-align: center; color: #6c757d; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Подтверждение email адреса</h2>
        </div>
        
        <div class="content">
            <p>Здравствуйте, {{ $user->name }}!</p>
            
            <p>Благодарим вас за регистрацию в нашем сервисе. Для завершения регистрации пожалуйста подтвердите ваш email адрес.</p>
            
            <p>Для завершения нажмите на кнопку ниже:</p>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ $verificationUrl }}" class="button">Подтвердить Email</a>
            </p>
            
            <p>Или скопируйте и вставьте в браузер следующую ссылку:</p>
            <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                {{ $verificationUrl }}
            </p>
            
            <p>Если вы не создавали аккаунт, пожалуйста проигнорируйте это письмо.</p>
            
            <hr>
            <p>С уважением,<br>Команда {{ config('app.name') }}</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. Все права защищены.</p>
        </div>
    </div>
</body>
</html>