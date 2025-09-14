<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Приглашение</title>
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
            <h2>Приглашение</h2>
        </div>
        
        <div class="content">
            <p>Здравствуйте!</p>
            
            <p>Вы были приглашены присоединиться к клиенту <strong>{{ $clientName }}</strong>.</p>
            
            <p>Для завершения нажмите на кнопку ниже:</p>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ $acceptUrl }}" class="button">Принять приглашение</a>
            </p>
            
            <p>Или скопируйте и вставьте в браузер следующую ссылку:</p>
            <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                {{ $acceptUrl }}
            </p>
            
            <p>Если это приглашение пришло к вам по ошибке, пожалуйста, проигнорируйте данное письмо.</p>
            
            <p>Ссылка действительна в течение 7 дней.</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. Все права защищены.</p>
        </div>
    </div>
</body>
</html>