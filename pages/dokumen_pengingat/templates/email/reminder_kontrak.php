<h3>Reminder Kontrak</h3>
<p>Kontrak berikut akan segera berakhir:</p>

<ul>
    <li>Vendor: <?= htmlspecialchars($d['nama_vendor']) ?></li>
    <li>No Kontrak: <?= htmlspecialchars($d['no_kontrak']) ?></li>
    <li>Expire Date: <?= $d['expire_date']->format('d-m-Y') ?></li>
</ul>

<p>Mohon segera ditindaklanjuti, setelah di perpanjang silahkan rubah expired date dokumen tersebut di http://192.168.7.184:8080/gg_app/login.php</p>
