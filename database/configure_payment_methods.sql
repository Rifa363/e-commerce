UPDATE payment_accounts
SET account_number = '8700195784',
    account_holder = 'RIRA CAKES MANIS LEGIT',
    is_active = TRUE
WHERE method = 'transfer' AND bank_name = 'BCA';

UPDATE payment_accounts
SET is_active = FALSE
WHERE method = 'transfer' AND bank_name <> 'BCA';

UPDATE payment_accounts
SET bank_name = 'QRIS',
    account_number = NULL,
    account_holder = 'RIRA CAKES MANIS LEGIT',
    qr_image_url = 'uploads/qris/qris-gopay-rira.png',
    is_active = TRUE
WHERE method = 'qris';
