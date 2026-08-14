<?php
session_start();
$host = 'localhost';
$db = 'transport_db';
$user = 'root'; 
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die('Erreur de connexion à la base de données');
}

if (isset($_POST['register'])) {
    $nom = htmlspecialchars($_POST['nom']);
    $prenom = htmlspecialchars($_POST['prenom']);
    $email = strtolower(trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $telephone = htmlspecialchars($_POST['tel']);
    $photo_profil = null;

    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo_profil']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('profil_') . '.' . $ext;
        $dest = 'Uploads/' . $filename;
        if (!is_dir('Uploads')) mkdir('Uploads');
        move_uploaded_file($_FILES['photo_profil']['tmp_name'], $dest);
        $photo_profil = $dest;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header('Location: register.html?error=exists');
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password, telephone, photo_profil) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nom, $prenom, $email, $password, $telephone, $photo_profil]);
    header('Location: login.html?register=success');
    exit;
}

if (isset($_POST['login'])) {
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['prenom'] = $user['prenom'];
        $_SESSION['photo_profil'] = $user['photo_profil'];
        $_SESSION['email'] = $user['email'];
        header('Location: profil.php');
        exit;
    } else {
        header('Location: login.html?error=1');
        exit;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.html');
    exit;
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

if (isset($_POST['reset_request'])) {
    $email = strtolower(trim($_POST['email']));
    
    // Vérifier si l'email existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $token = generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Stocker le token dans la base
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->execute([$token, $expires, $email]);
        
        // Créer le lien de réinitialisation
        $reset_link = "http://localhost/transport/reset_password.php?token=$token";
        
        // Envoyer l'email avec PHPMailer
        require 'PHPMailer/src/Exception.php';
        require 'PHPMailer/src/PHPMailer.php';
        require 'PHPMailer/src/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Configuration SMTP Gmail
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'elfred434@gmail.com'; // Remplacez par votre email Gmail
            $mail->Password = 'vbxtrryctowbipaj'; // Remplacez par le mot de passe d'application
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Activer le débogage SMTP (facultatif, pour tester)
            // $mail->SMTPDebug = 2;
            
            // Destinataire
            $mail->setFrom('votre.email@gmail.com', 'Agence de Transport');
            $mail->addAddress($email);
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = 'Réinitialisation de votre mot de passe';
            $mail->Body = "
                <h1>Réinitialisation de mot de passe</h1>
                <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                <a href='$reset_link'>$reset_link</a>
                <p>Ce lien expirera dans 1 heure.</p>
            ";
            
            $mail->send();
            
            // Stocker le lien en session pour le test
            $_SESSION['reset_link'] = $reset_link;
            header('Location: reset_request_sent.php');
            exit;
            
        } catch (Exception $e) {
            // Loguer l'erreur pour le débogage
            error_log("Erreur PHPMailer: " . $mail->ErrorInfo);
            header('Location: reset_request.html?error=mail');
            exit;
        }
    } else {
        // Email non trouvé
        header('Location: reset_request.html?error=email_not_found');
        exit;
    }
}

if (isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Vérifier le token
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Mettre à jour le mot de passe
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$password, $user['id']]);
        
        header('Location: login.html?reset=success');
        exit;
    } else {
        header('Location: reset_password.php?token='.$token.'&error=invalid_token');
        exit;
    }
}
?>