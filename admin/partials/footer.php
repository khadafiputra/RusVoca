<?php
/**
 * admin/partials/footer.php
 * Penutup layout panel admin.
 */
// Tolak akses langsung ke file partial ini.
if (!isset($koneksi)) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}
?>
</main>
</body>
</html>
