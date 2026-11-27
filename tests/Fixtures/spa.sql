CREATE TABLE hang_thanh_vien (
 ma_hang_thanh_vien INTEGER PRIMARY KEY AUTOINCREMENT,
 ten_hang VARCHAR(100) NOT NULL UNIQUE,
 ty_le_giam_gia DECIMAL(5,2) NOT NULL DEFAULT 0,
 dang_hoat_dong BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 CONSTRAINT ck_hang_thanh_vien_1 CHECK(ty_le_giam_gia BETWEEN 0 AND 100)
);
CREATE TABLE nguoi_dung (
 ma_nguoi_dung INTEGER PRIMARY KEY AUTOINCREMENT,
 ten_nguoi_dung VARCHAR(150) NOT NULL,
 dia_chi_thu_dien_tu VARCHAR(190) NULL UNIQUE,
 so_dien_thoai VARCHAR(25) NULL,
 mat_khau VARCHAR(255) NULL,
 vai_tro TEXT NOT NULL DEFAULT 'customer',
 ma_hang_thanh_vien INT NULL,
 co_quyen_tiep_nhan BOOLEAN NOT NULL DEFAULT FALSE,
 co_quyen_thu_tien BOOLEAN NOT NULL DEFAULT FALSE,
 phien_ban INT NOT NULL DEFAULT 1,
 dang_hoat_dong BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 CONSTRAINT ck_nguoi_dung_nhiem_vu CHECK(vai_tro <> 'customer' OR (co_quyen_tiep_nhan=FALSE AND co_quyen_thu_tien=FALSE)),
 CONSTRAINT ck_nguoi_dung_phien_ban CHECK(phien_ban>0)
);
CREATE TABLE danh_muc_dich_vu (
 ma_danh_muc INTEGER PRIMARY KEY AUTOINCREMENT,
 ten_danh_muc VARCHAR(150) NOT NULL,
 mo_ta TEXT NULL,
 dang_hoat_dong BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL
);
CREATE TABLE dich_vu (
 ma_dich_vu INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_danh_muc INT NOT NULL,
 ten_dich_vu VARCHAR(150) NOT NULL,
 mo_ta TEXT NULL,
 gia DECIMAL(12,2) NOT NULL,
 thoi_luong_phut INT NOT NULL,
 dang_hoat_dong BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_danh_muc) REFERENCES danh_muc_dich_vu(ma_danh_muc),
 CONSTRAINT ck_dich_vu_1 CHECK(gia>=0),
 CONSTRAINT ck_dich_vu_2 CHECK(thoi_luong_phut>0)
);
CREATE TABLE lich_lam_viec (
 ma_lich_lam_viec INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_nhan_vien INT NOT NULL,
 ngay_lam_viec DATE NOT NULL,
 gio_bat_dau TIME NOT NULL,
 gio_ket_thuc TIME NOT NULL,
 co_the_nhan_lich BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_nhan_vien) REFERENCES nguoi_dung(ma_nguoi_dung),
 CONSTRAINT ck_lich_lam_viec_1 CHECK(gio_bat_dau<gio_ket_thuc)
);
CREATE TABLE phong (
 ma_phong INTEGER PRIMARY KEY AUTOINCREMENT,
 ten_phong VARCHAR(100) NOT NULL UNIQUE,
 dang_hoat_dong BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL
);
CREATE TABLE giuong (
 ma_giuong INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_phong INT NOT NULL,
 ten_giuong VARCHAR(100) NOT NULL,
 dang_hoat_dong BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_phong) REFERENCES phong(ma_phong),
 UNIQUE(ma_phong,ten_giuong)
);
CREATE TABLE goi_lieu_trinh (
 ma_goi_lieu_trinh INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_dich_vu INT NOT NULL,
 ten_goi VARCHAR(150) NOT NULL,
 tong_so_buoi INT NOT NULL,
 gia DECIMAL(12,2) NOT NULL,
 dang_hoat_dong BOOLEAN NOT NULL DEFAULT TRUE,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_dich_vu) REFERENCES dich_vu(ma_dich_vu),
 CONSTRAINT ck_goi_lieu_trinh_1 CHECK(tong_so_buoi>0),
 CONSTRAINT ck_goi_lieu_trinh_2 CHECK(gia>=0)
);
CREATE TABLE goi_da_mua (
 ma_goi_da_mua INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_khach_hang INT NOT NULL,
 ma_goi_lieu_trinh INT NOT NULL,
 ma_nguoi_ban INT NULL,
 thoi_diem_mua DATETIME NOT NULL,
 ma_dich_vu INT NOT NULL,
 tong_so_buoi INT NOT NULL,
 so_buoi_da_dung INT NOT NULL DEFAULT 0,
 gia_mua DECIMAL(12,2) NOT NULL,
 gia_tri_cap_von DECIMAL(12,2) NOT NULL,
 ten_goi_luc_mua VARCHAR(150) NOT NULL,
 ten_dich_vu_luc_mua VARCHAR(150) NOT NULL,
 phien_ban INT NOT NULL DEFAULT 1,
 trang_thai TEXT NOT NULL DEFAULT 'pending_payment',
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_khach_hang) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_goi_lieu_trinh) REFERENCES goi_lieu_trinh(ma_goi_lieu_trinh),
 FOREIGN KEY(ma_dich_vu) REFERENCES dich_vu(ma_dich_vu),
 FOREIGN KEY(ma_nguoi_ban) REFERENCES nguoi_dung(ma_nguoi_dung) ON DELETE SET NULL,
 CONSTRAINT ck_goi_da_mua_1 CHECK(tong_so_buoi>0 AND so_buoi_da_dung>=0 AND so_buoi_da_dung<=tong_so_buoi),
 CONSTRAINT ck_goi_da_mua_2 CHECK(gia_mua>=0 AND gia_tri_cap_von>=0 AND gia_tri_cap_von<=gia_mua AND phien_ban>0)
);
CREATE TABLE lich_hen (
 ma_lich_hen INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_khach_hang INT NOT NULL,
 ma_nhan_vien INT NULL,
 ma_giuong INT NULL,
 ma_dich_vu INT NOT NULL,
 ma_goi_da_mua INT NULL,
 ma_nguoi_tao_lich INT NULL,
 nguon_dat_lich TEXT NOT NULL DEFAULT 'online',
 thoi_diem_bat_dau DATETIME NOT NULL,
 thoi_diem_ket_thuc DATETIME NOT NULL,
 trang_thai TEXT NOT NULL DEFAULT 'pending',
 ghi_chu_khach_hang TEXT NULL,
 ghi_chu_nhan_vien TEXT NULL,
 thoi_diem_hoan_thanh DATETIME NULL,
 ma_nguoi_hoan_thanh INT NULL,
 ten_dich_vu_luc_dat VARCHAR(150) NOT NULL,
 gia_dich_vu_luc_dat DECIMAL(12,2) NOT NULL,
 phien_ban INT NOT NULL DEFAULT 1,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_khach_hang) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_nhan_vien) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_giuong) REFERENCES giuong(ma_giuong),
 FOREIGN KEY(ma_dich_vu) REFERENCES dich_vu(ma_dich_vu),
 FOREIGN KEY(ma_goi_da_mua) REFERENCES goi_da_mua(ma_goi_da_mua),
 FOREIGN KEY(ma_nguoi_tao_lich) REFERENCES nguoi_dung(ma_nguoi_dung) ON DELETE SET NULL,
 FOREIGN KEY(ma_nguoi_hoan_thanh) REFERENCES nguoi_dung(ma_nguoi_dung),
 CONSTRAINT ck_lich_hen_snapshot CHECK(gia_dich_vu_luc_dat>=0 AND phien_ban>0),
 CONSTRAINT ck_lich_hen_1 CHECK(thoi_diem_bat_dau<thoi_diem_ket_thuc)
);
CREATE TABLE luot_su_dung_goi (
 ma_luot_su_dung INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_goi_da_mua INT NOT NULL,
 ma_lich_hen INT NOT NULL UNIQUE,
 ma_nguoi_ghi_nhan INT NULL,
 thoi_diem_su_dung DATETIME NOT NULL,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_goi_da_mua) REFERENCES goi_da_mua(ma_goi_da_mua),
 FOREIGN KEY(ma_lich_hen) REFERENCES lich_hen(ma_lich_hen),
 FOREIGN KEY(ma_nguoi_ghi_nhan) REFERENCES nguoi_dung(ma_nguoi_dung) ON DELETE SET NULL
);
CREATE TABLE hoa_don (
 ma_hoa_don INTEGER PRIMARY KEY AUTOINCREMENT,
 so_hoa_don VARCHAR(40) NOT NULL UNIQUE,
 ma_khach_hang INT NOT NULL,
 ma_lich_hen INT NULL UNIQUE,
 ma_goi_da_mua INT NULL UNIQUE,
 ma_nguoi_lap INT NULL,
 tien_truoc_giam DECIMAL(12,2) NOT NULL,
 tien_giam DECIMAL(12,2) NOT NULL DEFAULT 0,
 ten_hang_luc_lap VARCHAR(100) NULL,
 ty_le_giam_luc_lap DECIMAL(5,2) NOT NULL DEFAULT 0,
 thanh_tien DECIMAL(12,2) NOT NULL,
 ten_noi_dung_luc_lap VARCHAR(200) NOT NULL,
 credit_chuyen_goi DECIMAL(12,2) NOT NULL DEFAULT 0,
 tien_can_thu DECIMAL(12,2) NOT NULL,
 so_tien_da_thu DECIMAL(12,2) NOT NULL DEFAULT 0,
 ma_nguoi_thu INT NULL,
 trang_thai_xu_ly TEXT NOT NULL DEFAULT 'normal',
 phien_ban INT NOT NULL DEFAULT 1,
 phuong_thuc_thanh_toan TEXT NULL,
 trang_thai_thanh_toan TEXT NOT NULL DEFAULT 'unpaid',
 thoi_diem_thanh_toan DATETIME NULL,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_khach_hang) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_lich_hen) REFERENCES lich_hen(ma_lich_hen),
 FOREIGN KEY(ma_goi_da_mua) REFERENCES goi_da_mua(ma_goi_da_mua),
 FOREIGN KEY(ma_nguoi_lap) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_nguoi_thu) REFERENCES nguoi_dung(ma_nguoi_dung),
 CONSTRAINT ck_hoa_don_1 CHECK(tien_truoc_giam>=0 AND tien_giam>=0 AND tien_giam<=tien_truoc_giam AND thanh_tien=tien_truoc_giam-tien_giam),
 CONSTRAINT ck_hoa_don_2 CHECK(ty_le_giam_luc_lap BETWEEN 0 AND 100),
 CONSTRAINT ck_hoa_don_3 CHECK(
  (trang_thai_thanh_toan='unpaid' AND so_tien_da_thu=0 AND phuong_thuc_thanh_toan IS NULL AND thoi_diem_thanh_toan IS NULL AND ma_nguoi_thu IS NULL)
  OR
  (trang_thai_thanh_toan='paid' AND so_tien_da_thu=tien_can_thu AND thoi_diem_thanh_toan IS NOT NULL AND ma_nguoi_thu IS NOT NULL AND phuong_thuc_thanh_toan IS NOT NULL AND
   ((tien_can_thu=0 AND phuong_thuc_thanh_toan='no_charge') OR (tien_can_thu>0 AND phuong_thuc_thanh_toan IN ('cash','bank_transfer'))))
 ),
 CONSTRAINT ck_hoa_don_credit CHECK(credit_chuyen_goi>=0 AND credit_chuyen_goi<=thanh_tien AND tien_can_thu=thanh_tien-credit_chuyen_goi AND so_tien_da_thu>=0 AND phien_ban>0 AND (credit_chuyen_goi=0 OR ma_goi_da_mua IS NOT NULL)),
 CONSTRAINT ck_hoa_don_4 CHECK((ma_lich_hen IS NOT NULL AND ma_goi_da_mua IS NULL) OR (ma_lich_hen IS NULL AND ma_goi_da_mua IS NOT NULL))
);
CREATE TABLE nhac_hen (
 ma_nhac_hen INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_lich_hen INT NOT NULL,
 loai_nhac TEXT NOT NULL DEFAULT 'before_24h',
 thoi_diem_hen_luu_lai DATETIME NOT NULL,
 trang_thai TEXT NOT NULL DEFAULT 'pending',
 thoi_diem_du_kien_gui DATETIME NOT NULL,
 thoi_diem_da_gui DATETIME NULL,
 thoi_diem_bat_dau_xu_ly DATETIME NULL,
 so_lan_thu_gui INT NOT NULL DEFAULT 0,
 loi_gan_nhat TEXT NULL,
 thoi_diem_tao TIMESTAMP NULL,
 thoi_diem_cap_nhat TIMESTAMP NULL,
 FOREIGN KEY(ma_lich_hen) REFERENCES lich_hen(ma_lich_hen),
 UNIQUE(ma_lich_hen,loai_nhac),
 CONSTRAINT ck_nhac_hen_so_lan_thu_gui CHECK(so_lan_thu_gui>=0)
);
CREATE TABLE chuyen_goi (
 ma_chuyen_goi INTEGER PRIMARY KEY AUTOINCREMENT,
 khoa_chong_lap VARCHAR(64) NOT NULL UNIQUE,
 ma_khach_hang INT NOT NULL,
 ma_goi_nguon INT NOT NULL,
 ma_mau_goi_dich INT NOT NULL,
 ma_dich_vu_dich_luc_duyet INT NULL,
 ma_goi_dich INT NULL UNIQUE,
 ma_hoa_don INT NULL UNIQUE,
 ma_nguoi_de_nghi INT NOT NULL,
 ma_nguoi_duyet INT NULL,
 ma_nguoi_ket_thuc INT NULL,
 trang_thai TEXT NOT NULL DEFAULT 'pending',
 nguon_dang_xu_ly INT GENERATED ALWAYS AS (CASE WHEN trang_thai IN ('pending','awaiting_payment') THEN ma_goi_nguon ELSE NULL END) STORED,
 trang_thai_nguon_truoc_khoa TEXT NULL,
 ly_do_de_nghi TEXT NOT NULL,
 ly_do_ket_thuc TEXT NULL,
 so_buoi_nguon INT NULL,
 so_buoi_da_dung_luc_duyet INT NULL,
 gia_tri_cap_von_nguon DECIMAL(12,2) NULL,
 gia_tri_chuyen DECIMAL(12,2) NULL,
 ten_goi_dich_luc_duyet VARCHAR(150) NULL,
 ten_dich_vu_dich_luc_duyet VARCHAR(150) NULL,
 so_buoi_goi_dich INT NULL,
 gia_truoc_giam_goi_dich DECIMAL(12,2) NULL,
 tien_giam_goi_dich DECIMAL(12,2) NULL,
 ten_hang_luc_duyet VARCHAR(100) NULL,
 ty_le_giam_luc_duyet DECIMAL(5,2) NULL,
 gia_goi_dich_sau_giam DECIMAL(12,2) NULL,
 tien_thu_them DECIMAL(12,2) NULL,
 phien_ban_chinh_sach VARCHAR(30) NULL,
 quy_tac_lam_tron VARCHAR(30) NULL,
 phien_ban_nguon_luc_duyet INT NULL,
 thoi_diem_khach_dong_y DATETIME NULL,
 thoi_diem_de_nghi DATETIME NOT NULL,
 thoi_diem_duyet DATETIME NULL,
 thoi_diem_ket_thuc DATETIME NULL,
 phien_ban INT NOT NULL DEFAULT 1,
 UNIQUE(nguon_dang_xu_ly),
 FOREIGN KEY(ma_khach_hang) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_goi_nguon) REFERENCES goi_da_mua(ma_goi_da_mua),
 FOREIGN KEY(ma_mau_goi_dich) REFERENCES goi_lieu_trinh(ma_goi_lieu_trinh),
 FOREIGN KEY(ma_dich_vu_dich_luc_duyet) REFERENCES dich_vu(ma_dich_vu),
 FOREIGN KEY(ma_goi_dich) REFERENCES goi_da_mua(ma_goi_da_mua),
 FOREIGN KEY(ma_hoa_don) REFERENCES hoa_don(ma_hoa_don),
 FOREIGN KEY(ma_nguoi_de_nghi) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_nguoi_duyet) REFERENCES nguoi_dung(ma_nguoi_dung),
 FOREIGN KEY(ma_nguoi_ket_thuc) REFERENCES nguoi_dung(ma_nguoi_dung),
 CONSTRAINT ck_chuyen_goi_khac CHECK(ma_goi_dich IS NULL OR ma_goi_dich<>ma_goi_nguon),
 CONSTRAINT ck_chuyen_goi_phien_ban CHECK(phien_ban>0),
 CONSTRAINT ck_chuyen_goi_gia_tri CHECK(
   trang_thai NOT IN ('awaiting_payment','completed') OR
   (so_buoi_nguon IS NOT NULL AND so_buoi_nguon>0 AND so_buoi_da_dung_luc_duyet IS NOT NULL AND so_buoi_da_dung_luc_duyet>=0 AND so_buoi_da_dung_luc_duyet<so_buoi_nguon
    AND gia_tri_cap_von_nguon IS NOT NULL AND gia_tri_cap_von_nguon>=0
    AND gia_tri_chuyen IS NOT NULL AND gia_tri_chuyen=FLOOR(gia_tri_cap_von_nguon*(so_buoi_nguon-so_buoi_da_dung_luc_duyet)/so_buoi_nguon)
    AND gia_goi_dich_sau_giam IS NOT NULL AND gia_goi_dich_sau_giam>=gia_tri_chuyen
    AND gia_truoc_giam_goi_dich IS NOT NULL AND tien_giam_goi_dich IS NOT NULL AND gia_truoc_giam_goi_dich>=0 AND tien_giam_goi_dich BETWEEN 0 AND gia_truoc_giam_goi_dich
    AND gia_goi_dich_sau_giam=gia_truoc_giam_goi_dich-tien_giam_goi_dich
    AND tien_thu_them IS NOT NULL AND tien_thu_them=gia_goi_dich_sau_giam-gia_tri_chuyen
    AND so_buoi_goi_dich IS NOT NULL AND so_buoi_goi_dich>0
    AND ty_le_giam_luc_duyet IS NOT NULL AND ty_le_giam_luc_duyet BETWEEN 0 AND 100
    AND ma_dich_vu_dich_luc_duyet IS NOT NULL AND ten_goi_dich_luc_duyet IS NOT NULL AND ten_dich_vu_dich_luc_duyet IS NOT NULL
    AND phien_ban_chinh_sach IS NOT NULL AND quy_tac_lam_tron IS NOT NULL AND quy_tac_lam_tron='floor_vnd'
    AND phien_ban_nguon_luc_duyet IS NOT NULL AND phien_ban_nguon_luc_duyet>0
    AND trang_thai_nguon_truoc_khoa IS NOT NULL
    AND ma_nguoi_duyet IS NOT NULL AND thoi_diem_duyet IS NOT NULL AND thoi_diem_khach_dong_y IS NOT NULL)
 ),
 CONSTRAINT ck_chuyen_goi_hoan_tat CHECK(
  (trang_thai='completed' AND ma_goi_dich IS NOT NULL AND ma_hoa_don IS NOT NULL AND ma_nguoi_ket_thuc IS NOT NULL AND thoi_diem_ket_thuc IS NOT NULL)
  OR (trang_thai<>'completed' AND ma_goi_dich IS NULL AND ma_hoa_don IS NULL)
 ),
 CONSTRAINT ck_chuyen_goi_tu_choi CHECK(trang_thai<>'rejected' OR (ma_nguoi_ket_thuc IS NOT NULL AND thoi_diem_ket_thuc IS NOT NULL AND ly_do_ket_thuc IS NOT NULL))
);
CREATE TABLE nhat_ky_hoa_don (
 ma_nhat_ky INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_hoa_don INT NOT NULL,
 ma_nguoi_thao_tac INT NOT NULL,
 trang_thai_truoc TEXT NOT NULL,
 trang_thai_sau TEXT NOT NULL,
 ly_do TEXT NOT NULL,
 noi_dung_doi_soat TEXT NULL,
 thoi_diem_thao_tac DATETIME NOT NULL,
 phien_ban_hoa_don INT NOT NULL,
 FOREIGN KEY(ma_hoa_don) REFERENCES hoa_don(ma_hoa_don),
 FOREIGN KEY(ma_nguoi_thao_tac) REFERENCES nguoi_dung(ma_nguoi_dung),
 CONSTRAINT ck_nhat_ky_hoa_don_thay_doi CHECK(trang_thai_truoc<>trang_thai_sau AND phien_ban_hoa_don>0)
);
CREATE TABLE nhat_ky_quan_tri (
 ma_nhat_ky INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_nguoi_thao_tac INT NOT NULL,
 loai_doi_tuong TEXT NOT NULL,
 ma_doi_tuong INT NOT NULL,
 hanh_dong VARCHAR(60) NOT NULL,
 du_lieu_truoc JSON NULL,
 du_lieu_sau JSON NULL,
 ly_do TEXT NOT NULL,
 thoi_diem_thao_tac DATETIME NOT NULL,
 khoa_yeu_cau VARCHAR(64) NOT NULL UNIQUE,
 FOREIGN KEY(ma_nguoi_thao_tac) REFERENCES nguoi_dung(ma_nguoi_dung)
);
CREATE TABLE nhat_ky_lich_hen (
 ma_nhat_ky INTEGER PRIMARY KEY AUTOINCREMENT,
 ma_lich_hen INT NOT NULL,
 ma_nguoi_thao_tac INT NOT NULL,
 du_lieu_truoc JSON NOT NULL,
 du_lieu_sau JSON NOT NULL,
 ly_do TEXT NOT NULL,
 thoi_diem_thao_tac DATETIME NOT NULL,
 phien_ban_lich INT NOT NULL,
 FOREIGN KEY(ma_lich_hen) REFERENCES lich_hen(ma_lich_hen),
 FOREIGN KEY(ma_nguoi_thao_tac) REFERENCES nguoi_dung(ma_nguoi_dung)
);
