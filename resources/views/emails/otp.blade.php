<!DOCTYPE html>
<html>
<head>
    <title>Kode Verifikasi OTP Anda</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #333333; text-align: center;">Verifikasi Email Anda</h2>
        <p style="color: #555555; font-size: 16px; line-height: 1.5; text-align: center;">
            Gunakan kode OTP berikut untuk menyelesaikan proses pendaftaran Anda. Kode ini berlaku selama 10 menit.
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; color: #2563eb; letter-spacing: 5px; padding: 15px 30px; background-color: #eff6ff; border-radius: 8px; border: 2px dashed #bfdbfe;">
                {{ $otp }}
            </span>
        </div>
        <p style="color: #888888; font-size: 14px; text-align: center; line-height: 1.5;">
            Jika Anda tidak merasa melakukan pendaftaran, abaikan email ini.
        </p>
        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 30px 0;">
        <p style="color: #aaaaaa; font-size: 12px; text-align: center;">
            &copy; {{ date('Y') }} AyoMagang. Semua Hak Dilindungi.
        </p>
    </div>
</body>
</html>
