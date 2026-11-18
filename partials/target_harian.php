<?php
/**
 * partials/target_harian.php
 * ---------------------------------------------------------------------------
 * Widget kecil target belajar harian + heartbeat. Ditampilkan untuk siswa
 * pada semua halaman utama. Target yang sudah terpenuhi memunculkan
 * pemberitahuan, dan logout siswa ditahan sampai target hari ini tercapai.
 * ---------------------------------------------------------------------------
 */
if (!isset($koneksi)) { return; }

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/progres.php';

$uid_target = (int)($_SESSION['user_id'] ?? 0);
if ($uid_target <= 0 || pengguna_admin($koneksi)) { return; }

$data_target = durasi_harian_user($koneksi, $uid_target);
$label_target = function_exists('t') ? t('Target Belajar Hari Ini', 'Today\'s Learning Goal') : 'Target Belajar Hari Ini';
$label_menit  = function_exists('t') ? t('menit', 'minutes') : 'menit';
$label_tercapai = function_exists('t') ? t('Target tercapai!', 'Goal reached!') : 'Target tercapai!';
$pesan_tercapai = function_exists('t')
    ? t('Selamat! Kamu telah memenuhi target waktu pembelajaran hari ini.', 'Congratulations! You have met today\'s learning time goal.')
    : 'Selamat! Kamu telah memenuhi target waktu pembelajaran hari ini.';
$pesan_keluar = function_exists('t')
    ? t('Apakah Anda yakin keluar tetapi Anda belum mencapai target?', 'Are you sure you want to leave without reaching your target?')
    : 'Apakah Anda yakin keluar tetapi Anda belum mencapai target?';
?>
<style>
    .rv-target-widget {
        position: fixed; right: 18px; bottom: 18px; z-index: 80;
        width: 230px; background: #FFFFFF; border: 1px solid #E2E8F0;
        border-radius: 14px; padding: 12px 14px;
        box-shadow: 0 10px 26px rgba(15, 63, 122, .14);
        font-family: 'Plus Jakarta Sans', sans-serif; color: #0F172A;
    }
    .rv-target-widget .rv-tw-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 11px; font-weight: 800; color: #0F3F7A; margin-bottom: 8px; }
    .rv-target-widget .rv-tw-bar { height: 7px; border-radius: 999px; background: #E2E8F0; overflow: hidden; }
    .rv-target-widget .rv-tw-fill { display: block; height: 100%; width: 0%; border-radius: 999px; background: #E09B19; transition: width .4s ease; }
    .rv-target-widget.is-done .rv-tw-fill { background: #10B981; }
    .rv-target-widget .rv-tw-text { margin-top: 6px; font-size: 11px; color: #64748B; font-weight: 700; }
    .rv-target-widget .rv-tw-text b { color: #0F3F7A; }
    .rv-target-widget .rv-tw-badge { display: none; margin-top: 7px; font-size: 10px; font-weight: 800; color: #065F46; background: #D1FAE5; border: 1px solid #6EE7B7; border-radius: 999px; padding: 3px 8px; }
    .rv-target-widget.is-done .rv-tw-badge { display: inline-block; }
    .rv-target-toast {
        position: fixed; top: 18px; right: 18px; z-index: 120; max-width: 320px;
        background: #065F46; color: #FFFFFF; border-radius: 12px; padding: 13px 16px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 700; line-height: 1.5;
        box-shadow: 0 14px 30px rgba(6, 95, 70, .28);
        opacity: 0; transform: translateY(-12px); pointer-events: none; transition: .3s ease;
    }
    .rv-target-toast.is-show { opacity: 1; transform: translateY(0); }
    @media (max-width: 640px) {
        .rv-target-widget { right: 10px; bottom: 10px; width: 190px; padding: 10px 12px; }
        .rv-target-toast { left: 10px; right: 10px; max-width: none; }
    }
</style>
<div class="rv-target-widget<?= $data_target['tercapai'] ? ' is-done' : '' ?>" id="rvTargetWidget">
    <div class="rv-tw-head">
        <span><?= htmlspecialchars($label_target) ?></span>
        <span id="rvTargetPercent"><?= min(100, (int)round(($data_target['menit'] / max(1, $data_target['target'])) * 100)) ?>%</span>
    </div>
    <div class="rv-tw-bar"><span class="rv-tw-fill" id="rvTargetFill" style="width: <?= min(100, (int)round(($data_target['menit'] / max(1, $data_target['target'])) * 100)) ?>%"></span></div>
    <div class="rv-tw-text"><b id="rvTargetMenit"><?= (int)$data_target['menit'] ?></b> / <span id="rvTargetGoal"><?= (int)$data_target['target'] ?></span> <?= htmlspecialchars($label_menit) ?></div>
    <span class="rv-tw-badge" id="rvTargetBadge"><?= htmlspecialchars($label_tercapai) ?></span>
</div>
<div class="rv-target-toast" id="rvTargetToast" role="status" aria-live="polite">
    <?= htmlspecialchars($pesan_tercapai) ?>
</div>
<script>
window.RUSVOCA_TARGET = {
    endpoint: 'catat_belajar.php',
    csrf: <?= json_encode(csrf_token()) ?>,
    menit: <?= (int)$data_target['menit'] ?>,
    target: <?= (int)$data_target['target'] ?>,
    tercapai: <?= $data_target['tercapai'] ? 'true' : 'false' ?>,
    tanggal: <?= json_encode(date('Y-m-d')) ?>,
    pesanTercapai: <?= json_encode($pesan_tercapai) ?>,
    pesanKeluar: <?= json_encode($pesan_keluar) ?>
};
</script>
<script src="assets/js/target-harian.js"></script>
