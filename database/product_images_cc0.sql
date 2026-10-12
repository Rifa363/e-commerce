UPDATE products
SET image_url = CASE name
    WHEN 'Red Velvet Dream' THEN 'https://live.staticflickr.com/65535/54042719332_5fc67ecbcc_b.jpg'
    WHEN 'Chocolate Lava Cake' THEN 'https://live.staticflickr.com/65535/51716904256_18c4e35c7f_b.jpg'
    WHEN 'Classic Nastar Premium' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b0/Tat_nanas_%28Pineapple_tart%29_20230425_084201.jpg'
    WHEN 'Croissant Butter Almond' THEN 'https://pd.w.org/2023/02/2663f1e169ea6065.20469854-1536x2048.jpg'
    WHEN 'Tiramisu Cup' THEN 'https://pd.w.org/2026/06/1366a2adff62c60c6.47039487-1775x2048.jpg'
    WHEN 'Kastengel Keju Edam' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvZmY0ODUxLWltYWdlLWt3dnlpazZrLmpwZw.jpg'
    WHEN 'Lapis Legit Pandan' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Klepon Cake' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Bolu Kukus Mekar' THEN 'https://live.staticflickr.com/9/12197033_6f170c9814_b.jpg'
    WHEN 'Kue Lumpur Kentang' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b7/Childhood_taro_cake_%281%29.jpg'
    WHEN 'Dadar Gulung Pandan' THEN 'https://live.staticflickr.com/65535/49322379022_6f2b552767_b.jpg'
    WHEN 'Onde-Onde Wijen' THEN 'https://upload.wikimedia.org/wikipedia/commons/1/1f/Black_sesame_balls_%281%29.jpg'
    WHEN 'Putu Ayu Kelapa' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Lemper Ayam Suwir' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Three-color_glutinous_rice_cake.jpg'
    WHEN 'Pastel Ayam Sayur' THEN 'https://live.staticflickr.com/4539/26810206209_70e167cac4_b.jpg'
    WHEN 'Risoles Mayo Smoked Beef' THEN 'https://live.staticflickr.com/2/1893350_a2f0aef5ba_b.jpg'
    WHEN 'Serabi Solo' THEN 'https://live.staticflickr.com/65535/49322379022_6f2b552767_b.jpg'
    WHEN 'Apem Gula Merah' THEN 'https://live.staticflickr.com/9/12197033_6f170c9814_b.jpg'
    WHEN 'Kue Talam Ubi Ungu' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b7/Childhood_taro_cake_%281%29.jpg'
    WHEN 'Nagasari Pisang' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvcHgxMTA3NzMyLWltYWdlLWt3eXI2dzBrLmpwZw.jpg'
    WHEN 'Brownies Kukus Keju' THEN 'https://live.staticflickr.com/7540/16013504652_8d3928ce3f_b.jpg'
    WHEN 'Bolu Pandan Santan' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Lapis Beras Pelangi' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Three-color_glutinous_rice_cake.jpg'
    WHEN 'Kue Ku Kacang Hijau' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b8/Red_bean_cakes.jpg'
    WHEN 'Bika Ambon Mini' THEN 'https://live.staticflickr.com/9/12197033_6f170c9814_b.jpg'
    WHEN 'Chiffon Pandan' THEN 'https://upload.wikimedia.org/wikipedia/commons/1/13/Blood_Orange_Chiffon_Cake_with_Mango_Curd_%28Unsplash%29.jpg'
    WHEN 'Putri Salju Almond' THEN 'https://live.staticflickr.com/65535/53486971493_60bf84f5aa_b.jpg'
    WHEN 'Sagu Keju Premium' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvZmY0ODUxLWltYWdlLWt3dnlpazZrLmpwZw.jpg'
    WHEN 'Lidah Kucing Vanilla' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/32/Danish-butter-cookies_%28ring-shaped%29.jpg'
    WHEN 'Cookies Choco Chip' THEN 'https://live.staticflickr.com/7206/6858180551_babaac4d46_b.jpg'
    WHEN 'Thumbprint Strawberry' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvcHgxMDU4MjE0LWltYWdlLWt3dnk2b2xuLmpwZw.jpg'
    WHEN 'Semprit Butter' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/32/Danish-butter-cookies_%28ring-shaped%29.jpg'
    WHEN 'Snowball Almond' THEN 'https://live.staticflickr.com/65535/53486971493_60bf84f5aa_b.jpg'
    WHEN 'Palmier Caramel' THEN 'https://upload.wikimedia.org/wikipedia/commons/f/f8/PRC_China_%E6%8B%BC%E5%A4%9A%E5%A4%9A_PinDuoDuo_goods_%E8%9D%B4%E8%9D%B6%E9%85%A5_HuDieSu_palmier_sweet_pastry_December_2025_R12S_01.jpg'
    WHEN 'Oatmeal Raisin Cookies' THEN 'https://live.staticflickr.com/5697/30782275262_e220dee39b_b.jpg'
    WHEN 'Coffee Butter Cookies' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvaXMxMDMzOC1pbWFnZS1rd3lzaGh4ci5qcGc.jpg'
    WHEN 'Peanut Butter Cookies' THEN 'https://live.staticflickr.com/65535/51521747189_5e6715dabd_b.jpg'
    WHEN 'Florentine Almond' THEN 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDIyLTA2L3Vwd2s2MTY2NTcyMC13aWtpbWVkaWEtaW1hZ2Uta293cjcxdGUuanBn.jpg'
    WHEN 'Cornflakes Madu' THEN 'https://live.staticflickr.com/5697/30782275262_e220dee39b_b.jpg'
    WHEN 'Kue Kacang Jadul' THEN 'https://upload.wikimedia.org/wikipedia/commons/5/53/Moroccan_Sweets.png'
    WHEN 'Cheese Straws' THEN 'https://upload.wikimedia.org/wikipedia/commons/0/04/Cheese_straws_-_Skipton%2C_North_Yorkshire%2C_England_-_DSC01040.jpg'
    WHEN 'Brownies Cookies' THEN 'https://live.staticflickr.com/7540/16013504652_8d3928ce3f_b.jpg'
    WHEN 'Coconut Macaroons' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d4/Filipino_Coconut_Macaroons.jpg'
    WHEN 'Biscotti Almond' THEN 'https://live.staticflickr.com/65535/53454414052_930dc76121_b.jpg'
    WHEN 'Speculoos Cinnamon Cookies' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/32/Danish-butter-cookies_%28ring-shaped%29.jpg'
    WHEN 'Macaron Vanilla' THEN 'https://live.staticflickr.com/513/19574196885_5db826f1aa_b.jpg'
    WHEN 'Pain au Chocolat' THEN 'https://live.staticflickr.com/7639/17005688181_e80fbed5e0_b.jpg'
    WHEN 'Danish Custard' THEN 'https://upload.wikimedia.org/wikipedia/commons/c/c9/Cheese_danish_-_Massachusetts.jpg'
    WHEN 'Cinnamon Roll' THEN 'https://live.staticflickr.com/7645/17056089741_d930d4f865_b.jpg'
    WHEN 'Garlic Bread' THEN 'https://live.staticflickr.com/2838/34381029365_db022f9d59_b.jpg'
    WHEN 'Roti Sobek Cokelat' THEN 'https://live.staticflickr.com/305/19236165651_c91c6b317b_b.jpg'
    WHEN 'Roti Sisir Butter' THEN 'https://live.staticflickr.com/2481/3544028525_53521e4757_b.jpg'
    WHEN 'Brioche Butter' THEN 'https://pd.w.org/2023/04/959642c1dbe025568.88008891-2048x1536.jpg'
    WHEN 'Sourdough Country Loaf' THEN 'https://live.staticflickr.com/2481/3544028525_53521e4757_b.jpg'
    WHEN 'Baguette Artisan' THEN 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9zdGF0aWMvaW1hZ2Uvd2Vic2l0ZS8yMDIyLTA0L2xyL2lzMTU4ODYtaW1hZ2Uta3d2d3RsNG4uanBn.jpg'
    WHEN 'Cheese Danish' THEN 'https://upload.wikimedia.org/wikipedia/commons/c/c9/Cheese_danish_-_Massachusetts.jpg'
    WHEN 'Apple Turnover' THEN 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9zdGF0aWMvaW1hZ2Uvd2Vic2l0ZS8yMDIyLTA0L2xyL2ZycGFzdHJ5X3Bhc3RyaWVzX2FwcGxlX2JpdGVzLWltYWdlLWt5YmF4ejY5LmpwZw.jpg'
    WHEN 'Egg Tart Portugis' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvZmw0OTgwMTA5NTkyMi1pbWFnZS1reWJlZzNueC5qcGc.jpg'
    WHEN 'Pain Suisse Vanilla' THEN 'https://live.staticflickr.com/7639/17005688181_e80fbed5e0_b.jpg'
    WHEN 'Blueberry Muffin' THEN 'https://live.staticflickr.com/2560/3711698510_d05f4f2f86_b.jpg'
    WHEN 'Banana Bread Walnut' THEN 'https://live.staticflickr.com/2913/33276667233_485a2158ef_b.jpg'
    WHEN 'Focaccia Rosemary' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/3d/Focaccia_genovese_su_tagliere_in_legno%2C_1.jpg'
    WHEN 'Pretzel Keju' THEN 'https://pd.w.org/2022/03/669623f7e659eb641.51199595-1536x2048.jpg'
    WHEN 'Bomboloni Cokelat' THEN 'https://live.staticflickr.com/65535/54280572676_034f1ce6f0_b.jpg'
    WHEN 'Pain aux Raisins' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d4/Pain_aux_raisins_%28cropped%29.jpg'
    WHEN 'Sausage Roll' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvYTAxOS1qYWt1YmstMDU5NC1zYXVzYWdlLXJvbGxzLmpwZw.jpg'
    ELSE image_url
END
WHERE name IN (
    'Red Velvet Dream', 'Chocolate Lava Cake', 'Classic Nastar Premium', 'Croissant Butter Almond',
    'Tiramisu Cup', 'Kastengel Keju Edam', 'Lapis Legit Pandan', 'Klepon Cake', 'Bolu Kukus Mekar',
    'Kue Lumpur Kentang', 'Dadar Gulung Pandan', 'Onde-Onde Wijen', 'Putu Ayu Kelapa', 'Lemper Ayam Suwir',
    'Pastel Ayam Sayur', 'Risoles Mayo Smoked Beef', 'Serabi Solo', 'Apem Gula Merah', 'Kue Talam Ubi Ungu',
    'Nagasari Pisang', 'Brownies Kukus Keju', 'Bolu Pandan Santan', 'Lapis Beras Pelangi', 'Kue Ku Kacang Hijau',
    'Bika Ambon Mini', 'Chiffon Pandan', 'Putri Salju Almond', 'Sagu Keju Premium', 'Lidah Kucing Vanilla',
    'Cookies Choco Chip', 'Thumbprint Strawberry', 'Semprit Butter', 'Snowball Almond', 'Palmier Caramel',
    'Oatmeal Raisin Cookies', 'Coffee Butter Cookies', 'Peanut Butter Cookies', 'Florentine Almond',
    'Cornflakes Madu', 'Kue Kacang Jadul', 'Cheese Straws', 'Brownies Cookies', 'Coconut Macaroons',
    'Biscotti Almond', 'Speculoos Cinnamon Cookies', 'Macaron Vanilla', 'Pain au Chocolat', 'Danish Custard',
    'Cinnamon Roll', 'Garlic Bread', 'Roti Sobek Cokelat', 'Roti Sisir Butter', 'Brioche Butter',
    'Sourdough Country Loaf', 'Baguette Artisan', 'Cheese Danish', 'Apple Turnover', 'Egg Tart Portugis',
    'Pain Suisse Vanilla', 'Blueberry Muffin', 'Banana Bread Walnut', 'Focaccia Rosemary', 'Pretzel Keju',
    'Bomboloni Cokelat', 'Pain aux Raisins', 'Sausage Roll'
);
