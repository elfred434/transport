<?php

session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voyage_id = intval($_POST['voyage_id']);
    $colis_id = intval($_POST['colis_id']);
    $to = $_POST['email'];
    $from = "no-reply@tonsite.com";
    $subject = "Demande de transport de colis";
    $message = "Bonjour,\n\nUn utilisateur souhaite vous contacter pour transporter son colis (ID: $colis_id).\nMerci de vous connecter à la plateforme pour plus de détails.";
    $headers = "From: $from\r\nReply-To: $from";
    mail($to, $subject, $message, $headers);
    echo "<p>Votre demande a été envoyée au transporteur.</p><a href='dashboard.php'>Retour au tableau de bord</a>";
} else {
    header('Location: dashboard.php');
    exit;
}
?>
<a href="mailto:<?= htmlspecialchars($v['email']) ?>?subject=Demande de transport de colis" class="btn-action"><i class="fa-solid fa-envelope"></i> Contacter</a>