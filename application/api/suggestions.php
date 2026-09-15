<?php

class SuggestionController
{
    public function send(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiJsonError('Method not allowed', 405);
        }

        $email = $_POST['email'] ?? '';
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';

        if (empty($email) || empty($title)) {
            apiJsonError('Email dan Judul Ujian wajib diisi', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            apiJsonError('Format email tidak valid', 400);
        }

        $fileTmpPath = null;
        $fileName = null;

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['attachment'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                apiJsonError('Terjadi kesalahan saat mengunggah file', 400);
            }

            if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                apiJsonError('Ukuran file terlalu besar. Maksimal 5MB.', 400);
            }

            $fileTmpPath = $file['tmp_name'];
            $fileName = $file['name'];
        }

        require_once APP . 'libs/mailer.php';
        $mailer = new Mailer();
        $success = $mailer->sendSuggestionEmail($email, $title, $description, $fileTmpPath, $fileName);

        if ($success) {
            apiRespond(['message' => 'Saran berhasil dikirim. Terima kasih atas masukan Anda!']);
        } else {
            apiJsonError('Gagal mengirim saran. Silakan coba lagi nanti.', 500);
        }
        
        return true;
    }
}
