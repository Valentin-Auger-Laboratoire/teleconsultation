<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Prépare un mailer SMTP à partir de config.local.php (vérification SSL/TLS active par défaut)
function creerMailer(array $config): PHPMailer
{
    $smtp = $config['smtp'];
    if (empty($smtp['host'])) {
        throw new Exception('SMTP non configuré');
    }
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtp['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp['user'];
    $mail->Password   = $smtp['pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int) $smtp['port'];
    $mail->Timeout    = 10;
    $mail->CharSet    = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($smtp['from'], $smtp['from_name']);
    $mail->isHTML(true);
    return $mail;
}

function envoyerCodeVerif(array $config, string $emailDestinataire, string $code): bool
{
    try {
        $mail = creerMailer($config);
        $mail->addAddress($emailDestinataire);
        $mail->Subject = 'Votre code de vérification SantéConnect';
        $mail->Body    = 'Bonjour,<br><br>Voici votre code de sécurité : <b>' . e($code) . '</b><br>Il est valable ' . DUREE_CODE_MINUTES . ' minutes.';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Envoi du code de vérification : ' . $e->getMessage());
        return false;
    }
}

function envoyerOrdonnanceMail(array $config, string $emailDestinataire, string $nomMedecin, string $contenuOrdonnance): bool
{
    try {
        $mail = creerMailer($config);
        $mail->addAddress($emailDestinataire);
        $mail->Subject = 'Nouvelle ordonnance - ' . $nomMedecin;
        // Contenu échappé, puis nl2br pour conserver les retours à la ligne
        $mail->Body    = '<h2>Votre ordonnance numérique</h2>
                          <p><b>' . e($nomMedecin) . '</b> vous a transmis une ordonnance :</p>
                          <div style="background: #f4f4f4; padding: 15px; border-left: 4px solid #007bff;">'
                          . nl2br(e($contenuOrdonnance)) .
                          '</div>';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Envoi de l\'ordonnance : ' . $e->getMessage());
        return false;
    }
}
