<?php

function getWhatsAppConfig() {
    $config = [
        'provider' => getenv('WHATSAPP_PROVIDER') ?: null,
        'api_token' => getenv('WHATSAPP_API_TOKEN') ?: null,
        'admin_phone' => getenv('WHATSAPP_ADMIN_PHONE') ?: '6281219868390'
    ];

    try {
        require_once __DIR__ . '/../config/database.php';
        $db = (new Database())->getConnection();
        $stmt = $db->query("SELECT provider, api_token, admin_phone FROM whatsapp_config WHERE is_active = 1 LIMIT 1");
        $storedConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($storedConfig) {
            $config['provider'] = $config['provider'] ?: $storedConfig['provider'];
            $config['api_token'] = $config['api_token'] ?: $storedConfig['api_token'];
            if (!getenv('WHATSAPP_ADMIN_PHONE')) {
                $config['admin_phone'] = $storedConfig['admin_phone'];
            }
        }
    } catch (Throwable $e) {
        error_log('WhatsApp gateway configuration could not be loaded: ' . $e->getMessage());
    }

    $config['provider'] = $config['provider'] ?: 'wa_link';
    $config['api_token'] = $config['api_token'] ?: '';
    $config['admin_phone'] = normalizeWhatsAppPhone($config['admin_phone']);
    return $config;
}

function normalizeWhatsAppPhone($phone) {
    $phone = preg_replace('/\D+/', '', (string)$phone);
    if (str_starts_with($phone, '0')) {
        $phone = '62' . substr($phone, 1);
    }
    if (strlen($phone) < 8 || strlen($phone) > 15) {
        throw new InvalidArgumentException('Nomor WhatsApp admin tidak valid.');
    }
    return $phone;
}

function isWhatsAppOtpDeliveryConfigured() {
    try {
        $config = getWhatsAppConfig();
        return in_array($config['provider'], ['fonnte', 'wablas'], true) && $config['api_token'] !== '';
    } catch (Throwable $e) {
        error_log('WhatsApp OTP availability check failed: ' . $e->getMessage());
        return false;
    }
}

function sendWhatsAppOtp($phone, $message) {
    $config = getWhatsAppConfig();
    if (!in_array($config['provider'], ['fonnte', 'wablas'], true) || $config['api_token'] === '') {
        error_log('Password reset WhatsApp delivery is not configured.');
        return false;
    }

    try {
        $normalizedPhone = normalizeWhatsAppPhone($phone);
        $result = $config['provider'] === 'fonnte'
            ? sendViaFonnte($normalizedPhone, $message, $config['api_token'])
            : sendViaWablas($normalizedPhone, $message, $config['api_token']);
        return $result['success'];
    } catch (Throwable $e) {
        error_log('Password reset WhatsApp delivery failed: ' . $e->getMessage());
        return false;
    }
}

function sendAdminWhatsAppNotification($message) {
    try {
        $config = getWhatsAppConfig();
        $result = [
            'success' => false,
            'method' => 'wa_link',
            'wa_link' => 'https://wa.me/' . $config['admin_phone'] . '?text=' . urlencode($message),
            'message' => 'Tautan WhatsApp siap dikirim secara manual.'
        ];

        if ($config['provider'] === 'wa_link') {
            return $result;
        }
        if (!in_array($config['provider'], ['fonnte', 'wablas'], true)) {
            error_log('Unknown WhatsApp provider; using manual WhatsApp link.');
            return $result;
        }
        if ($config['api_token'] === '') {
            error_log('WhatsApp gateway token is not configured; using manual WhatsApp link.');
            return $result;
        }

        try {
            $sent = $config['provider'] === 'fonnte'
                ? sendViaFonnte($config['admin_phone'], $message, $config['api_token'])
                : sendViaWablas($config['admin_phone'], $message, $config['api_token']);
            $sent['wa_link'] = $result['wa_link'];
            return $sent;
        } catch (Throwable $e) {
            error_log('WhatsApp gateway delivery failed; using manual WhatsApp link: ' . $e->getMessage());
            return $result;
        }
    } catch (Throwable $e) {
        error_log('WhatsApp notification failed: ' . $e->getMessage());
        return ['success' => false, 'method' => 'unavailable', 'wa_link' => null];
    }
}

function sendWhatsAppNotification($orderData) {
    return sendAdminWhatsAppNotification(formatOrderMessage($orderData));
}

function formatOrderMessage($data) {
    $itemsText = '';
    foreach ($data['items'] as $item) {
        $itemsText .= "• {$item['name']} x{$item['qty']} = Rp " .
            number_format($item['price'] * $item['qty'], 0, ',', '.') . "\n";
    }

    $paymentMethods = [
        'transfer' => 'Transfer Bank',
        'qris' => 'QRIS',
        'cod' => 'COD (Bayar di Tempat)'
    ];
    $shippingMethods = [
        'instant' => 'Instant (2-3 Jam)',
        'sameday' => 'Same Day',
        'reguler' => 'Reguler (1-2 Hari)'
    ];

    return "🎂 *PESANAN BARU - RiRa Cakes* 🎂\n\n" .
        "📋 *Kode Order:* #{$data['order_code']}\n" .
        "👤 *Customer:* {$data['customer_name']}\n" .
        "📱 *HP:* {$data['customer_phone']}\n" .
        "📍 *Alamat:* {$data['address']}\n\n" .
        "*Pesanan:*\n{$itemsText}\n" .
        "Subtotal: Rp " . number_format($data['subtotal'], 0, ',', '.') . "\n" .
        (!empty($data['discount']) ? "Diskon ({$data['voucher_code']}): Rp " .
            number_format($data['discount'], 0, ',', '.') . "\n" : "") .
        "Ongkir ({$shippingMethods[$data['shipping_method']]}): Rp " .
            number_format($data['shipping'], 0, ',', '.') . "\n" .
        "*TOTAL: Rp " . number_format($data['total'], 0, ',', '.') . "*\n\n" .
        "💳 *Pembayaran:* {$paymentMethods[$data['payment_method']]}\n\n" .
        "Mohon segera diproses. Terima kasih!";
}

function sendViaFonnte($phone, $message, $token) {
    return sendWhatsAppGatewayRequest(
        'https://api.fonnte.com/send',
        ['target' => $phone, 'message' => $message],
        ['Authorization: ' . $token],
        static function ($response) {
            return !empty($response['status']);
        }
    );
}

function sendViaWablas($phone, $message, $token) {
    return sendWhatsAppGatewayRequest(
        'https://solo.wablas.com/api/send-message',
        ['token' => $token, 'phone' => $phone, 'message' => $message],
        [],
        static function ($response) {
            return isset($response['data']);
        }
    );
}

function sendWhatsAppGatewayRequest($url, $fields, $headers, $isSuccessful) {
    $curl = curl_init();
    if ($curl === false) {
        throw new RuntimeException('WhatsApp gateway request could not be initialized.');
    }
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);
    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('WhatsApp gateway request failed: ' . $curlError);
    }
    $response = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($response)) {
        throw new RuntimeException('WhatsApp gateway returned an invalid response (HTTP ' . $status . ').');
    }
    return ['success' => (bool)$isSuccessful($response), 'method' => 'gateway', 'response' => $response];
}
?>
