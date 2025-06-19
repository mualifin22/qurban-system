<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

session_start();

include '../../includes/db.php';
include '../../includes/functions.php';

if (!isAdmin() && !isPanitia()) {
    redirectToLogin();
}

if (isset($_GET['id'])) {
    $id_to_delete = sanitizeInput($_GET['id']);

    $conn->begin_transaction();
    $related_niks = []; 
    $old_statuses = []; 

    try {
        $stmt_get_qurban_info = $conn->prepare("SELECT jenis_hewan, nik_peserta_tunggal FROM hewan_qurban WHERE id = ?");
        $stmt_get_qurban_info->bind_param("i", $id_to_delete);
        $stmt_get_qurban_info->execute();
        $qurban_info = $stmt_get_qurban_info->get_result()->fetch_assoc();
        $jenis_hewan = $qurban_info['jenis_hewan'] ?? '';
        $stmt_get_qurban_info->close();

        if ($jenis_hewan === 'kambing' && !empty($qurban_info['nik_peserta_tunggal'])) {
            $related_niks[] = $qurban_info['nik_peserta_tunggal'];
        } elseif ($jenis_hewan === 'sapi') {
            $stmt_get_sapi_participants = $conn->prepare("SELECT nik_warga FROM peserta_sapi WHERE id_hewan_qurban = ?");
            $stmt_get_sapi_participants->bind_param("i", $id_to_delete);
            $stmt_get_sapi_participants->execute();
            $result_sapi_participants = $stmt_get_sapi_participants->get_result();
            while($row = $result_sapi_participants->fetch_assoc()) {
                $related_niks[] = $row['nik_warga'];
            }
            $stmt_get_sapi_participants->close();
        }

        foreach (array_unique($related_niks) as $nik_check) {
            if (!empty($nik_check)) {
                $stmt_get_current_status = $conn->prepare("SELECT status_qurban FROM warga WHERE nik = ?");
                $stmt_get_current_status->bind_param("s", $nik_check);
                $stmt_get_current_status->execute();
                $result_current_status = $stmt_get_current_status->get_result();
                if ($result_current_status->num_rows > 0) {
                    $old_statuses[$nik_check] = $result_current_status->fetch_assoc()['status_qurban'];
                }
                $stmt_get_current_status->close();
            }
        }

        $stmt_delete_keuangan = $conn->prepare("DELETE FROM keuangan WHERE id_hewan_qurban = ?");
        $stmt_delete_keuangan->bind_param("i", $id_to_delete);
        $stmt_delete_keuangan->execute();
        $stmt_delete_keuangan->close();

        $stmt_delete_peserta_sapi = $conn->prepare("DELETE FROM peserta_sapi WHERE id_hewan_qurban = ?");
        $stmt_delete_peserta_sapi->bind_param("i", $id_to_delete);
        $stmt_delete_peserta_sapi->execute();
        $stmt_delete_peserta_sapi->close();

        $stmt_delete_qurban = $conn->prepare("DELETE FROM hewan_qurban WHERE id = ?");
        $stmt_delete_qurban->bind_param("i", $id_to_delete);
        $stmt_delete_qurban->execute();

        if ($stmt_delete_qurban->affected_rows > 0) {
            foreach (array_unique($related_niks) as $nik_check) {
                if (empty($nik_check)) continue;

                $stmt_check_kambing = $conn->prepare("SELECT COUNT(*) FROM hewan_qurban WHERE nik_peserta_tunggal = ?");
                $stmt_check_kambing->bind_param("s", $nik_check);
                $stmt_check_kambing->execute();
                $is_kambing_participant_elsewhere = ($stmt_check_kambing->get_result()->fetch_row()[0] > 0);
                $stmt_check_kambing->close();

                $stmt_check_sapi = $conn->prepare("SELECT COUNT(*) FROM peserta_sapi WHERE nik_warga = ?");
                $stmt_check_sapi->bind_param("s", $nik_check);
                $stmt_check_sapi->execute();
                $is_sapi_participant_elsewhere = ($stmt_check_sapi->get_result()->fetch_row()[0] > 0);
                $stmt_check_sapi->close();

                $stmt_check_panitia = $conn->prepare("SELECT status_panitia FROM warga WHERE nik = ?");
                $stmt_check_panitia->bind_param("s", $nik_check);
                $stmt_check_panitia->execute();
                $is_panitia = ($stmt_check_panitia->get_result()->fetch_assoc()['status_panitia'] ?? 0);
                $stmt_check_panitia->close();

                $new_status_qurban_for_warga = '';
                if ($is_kambing_participant_elsewhere || $is_sapi_participant_elsewhere) {
                    $new_status_qurban_for_warga = 'peserta';
                } elseif ($is_panitia) {
                    $new_status_qurban_for_warga = 'penerima'; // Asumsi panitia tetap penerima
                } else {
                    $new_status_qurban_for_warga = 'penerima'; // Default jika tidak ada lagi status khusus
                }

                if ($old_statuses[$nik_check] === 'peserta' && $old_statuses[$nik_check] !== $new_status_qurban_for_warga) {
                    $stmt_update_final_status = $conn->prepare("UPDATE warga SET status_qurban = ? WHERE nik = ?");
                    $stmt_update_final_status->bind_param("ss", $new_status_qurban_for_warga, $nik_check);
                    $stmt_update_final_status->execute();
                    if ($stmt_update_final_status->error) {
                        throw new mysqli_sql_exception("Error saat memperbarui status_qurban final untuk NIK " . $nik_check . ": " . $stmt_update_final_status->error);
                    }
                    $stmt_update_final_status->close();
                }
            }

            $conn->commit();
            $_SESSION['message'] = "Data hewan qurban dan data terkait berhasil dihapus.";
            $_SESSION['message_type'] = "success";
        } else {
            $conn->rollback();
            $_SESSION['message'] = "Gagal menghapus data hewan qurban atau tidak ditemukan.";
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete_qurban->close();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        foreach ($old_statuses as $nik_check => $old_status) {
            $stmt_rollback_status = $conn->prepare("UPDATE warga SET status_qurban = ? WHERE nik = ?");
            $stmt_rollback_status->bind_param("ss", $old_status, $nik_check);
            $stmt_rollback_status->execute();
            $stmt_rollback_status->close();
        }
        $_SESSION['message'] = "Terjadi kesalahan saat menghapus data hewan qurban: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }

} else {
    $_SESSION['message'] = "ID hewan qurban tidak ditemukan untuk dihapus.";
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>
