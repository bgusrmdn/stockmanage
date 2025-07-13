<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-body text-center">
            <img src="images/banner.png"
                alt="Selamat Datang"
                class="img-fluid banner-image mb-4">
            <h4 class="card-title mt-3"><b>Stock Opname GDRM!</b></h4>
            <p class="card-text">
                Gunakan menu navigasi di sebelah kiri untuk mengelola produk, mencatat transaksi barang masuk dan keluar, serta melihat laporan stok.
            </p>
        </div>
    </div>
</div>

<style>
    .banner-image {
        max-width: 100%;
        max-height: 300px;
        height: auto;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(12, 199, 137, 0.87);
    }

    /* Responsive breakpoints */
    @media (max-width: 768px) {
        .banner-image {
            max-height: 200px;
        }
    }

    @media (max-width: 480px) {
        .banner-image {
            max-height: 150px;
        }
    }

    /* Optional: Add hover effect */
    .banner-image:hover {
        transform: scale(1.02);
        transition: transform 0.3s ease;
    }
</style>