<?php
// pages/resep_obat/get_proint_resep_headers.php
// Query 1: Get Headers by Color ID
require_once '../../koneksi3.php';

header('Content-Type: application/json');

$colormsid = $_GET['id'] ?? '';

if (empty($colormsid)) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $sql = "
    SELECT DISTINCT
        c.colorcode, 
        c.colorname, 
        h.resepno, 
        h.resepseq, 
        h.resepdate, 
        h.reseptype, 
        h.resepprodcode, 
        h.resepprodname, 
        h.prdnmbr, 
        h.resephdid,
        h.transnmbr AS no_so,
        r.rtgcode,
        r.rtgname,
        st.statusdesc,
        s.cuscolor
    FROM pdresephd h
    INNER JOIN pdcolorms c 
        ON h.colormsid = c.colormsid
    INNER JOIN (
        SELECT colormsid, MAX(cuscolor) AS cuscolor
        FROM smprodtechdata
        GROUP BY colormsid
    ) s ON c.colormsid = s.colormsid
    INNER JOIN pdresepdt d 
        ON h.resephdid = d.resephdid
        AND d.rtgdesc IN ('RESEP PADDRY DYESTUFF', 'RESEP OBAT PUTIH') 
    LEFT JOIN pdrtgms r 
        ON d.rtgmsid = r.rtgmsid
    LEFT JOIN pdresepstatus st 
        ON h.resepstatusid = st.resepstatusid 
    WHERE
        h.colormsid = :id
    ORDER BY h.resepdate DESC
    ";

    $stmt = $conn3->prepare($sql);
    $stmt->bindParam(':id', $colormsid);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format Display
    // resepno, resepseq, reseptype, resepprodname
    $formatted = [];
    foreach($results as $row) {
        $row['display_text'] = "{$row['resepno']} (Seq: {$row['resepseq']}) - {$row['resepprodname']} [{$row['reseptype']}]";
        $formatted[] = $row;
    }

    echo json_encode(['results' => $formatted]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
