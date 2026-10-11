<?php
include 'config/database.php';
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 4");

$contact_message = '';
if (isset($_POST['send_contact'])) {
    $contact_message = 'Thank you! Your message has been received. We will contact you soon.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Collection Scheduler</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="landing-page">

    <header class="recircle-nav navbar-fixed" id="top-nav">
        <div class="recircle-nav-inner">
            <a href="#home" class="recircle-logo nav-anchor">SMARTWASTE</a>
            <nav class="recircle-menu">
                <a href="#home" class="nav-anchor active">Home</a>
                <a href="#information" class="nav-anchor">Information</a>
                <a href="#announcements" class="nav-anchor">Announcements</a>
                <a href="#about" class="nav-anchor">About</a>
                <a href="#contact" class="nav-anchor">Contact</a>
            </nav>
            <div class="recircle-auth">
                <a href="login.php" class="recircle-login">Login</a>
                <a href="register.php" class="recircle-register">Register</a>
            </div>
        </div>
    </header>

    <main class="scroll-container" id="scroll-container">

        <!-- HOME -->
        <section id="home" class="snap-section recircle-section section-home">
            <div class="split-layout">
                <div class="split-text">
                    <h1 class="home-title">Smart Waste Collection<br><span>Scheduler</span></h1>
                    <p class="home-desc">
                        A community platform to view neighbourhood collection schedules,
                        recycling information, campaigns, and service requests in one place.
                    </p>
                    <a href="register.php" class="recircle-btn">Get Started</a>
                    <p class="scroll-hint">Scroll to next section ↓</p>
                </div>
                <div class="split-media">
                    <img src="assets/recycle-illustration.jpg" alt="Recycling illustration" class="hero-illustration">
                </div>
            </div>
        </section>

        <!-- INFORMATION -->
        <section id="information" class="snap-section recircle-section section-panel">
            <h2 class="recircle-section-title">INFORMATION</h2>
            <div class="features-row">
                <article class="feature-item">
                    <div class="feature-icon simple-icon icon-recycle" aria-hidden="true"></div>
                    <h3>Recycling</h3>
                    <p>Paper, cardboard, plastic bottles, cans, and glass. Rinse items before placing in the recycling bin.</p>
                </article>
                <article class="feature-item">
                    <div class="feature-icon simple-icon icon-bin" aria-hidden="true"></div>
                    <h3>General Waste</h3>
                    <p>Non-recyclable household waste. Tie bags securely and place bins outside by 7:00 AM on collection day.</p>
                </article>
                <article class="feature-item">
                    <div class="feature-icon simple-icon icon-calendar" aria-hidden="true"></div>
                    <h3>Resident Portal</h3>
                    <p>Login to view schedules, set reminders, request pickup, submit complaints, and read announcements.</p>
                </article>
            </div>
        </section>

        <!-- ANNOUNCEMENTS -->
        <section id="announcements" class="snap-section recircle-section section-panel section-alt">
            <h2 class="recircle-section-title">ANNOUNCEMENTS</h2>
            <p class="recircle-section-sub">Latest recycling campaigns and community updates</p>
            <div class="announcement-row">
                <?php
                if ($announcements && $announcements->num_rows > 0) {
                    while ($a = $announcements->fetch_assoc()) {
                        ?>
                        <article class="announce-card">
                            <span class="announce-tag"><?php echo htmlspecialchars($a['type']); ?></span>
                            <h3><?php echo htmlspecialchars($a['title']); ?></h3>
                            <p class="announce-date"><?php echo htmlspecialchars(date('d M Y', strtotime($a['created_at']))); ?></p>
                            <p><?php
                            $preview = strip_tags($a['content']);
                            if (strlen($preview) > 140) {
                                $preview = substr($preview, 0, 140) . '...';
                            }
                            echo htmlspecialchars($preview);
                            ?></p>
                        </article>
                        <?php
                    }
                } else {
                    echo '<p class="muted center">No announcements yet.</p>';
                }
                ?>
            </div>
        </section>

        <!-- ABOUT -->
        <section id="about" class="snap-section recircle-section section-panel">
            <div class="split-layout about-layout">
                <div class="split-media">
                    <img src="assets/recycle-illustration.jpg" alt="About SmartWaste" class="about-illustration">
                </div>
                <div class="split-text about-text">
                    <h2 class="about-heading">About SMARTWASTE</h2>
                    <p>
                        Smart Waste Collection Scheduler is a web-based platform designed to make waste collection management easier for residents. 
                        Residents can check colelction schedules, receive reminders, request pickups, report complaints, and stay updated with recycling campaigns and announcement. The system aims to improve communication between residents and waste collection administrators while encouraging better waste managements practices.
                    </p>
                    <p>
                        The system supports schedule viewing by neighbourhood, reminder alerts, pickup requests,
                        complaint tracking, and recycling awareness campaigns.
                    </p>

                    <div class="vision-block">
                        <svg class="mini-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
    </svg>
                        <div>
                            <h3>Vision</h3>
                            <ul class="recycle-list">
                                <li>Cleaner neighbourhoods through organised waste collection</li>
                                <li>Encourage more organised and efficient waste collection</li>
                                <li>Transparent communication between residents and administrators</li>
                            </ul>
                        </div>
                    </div>

                    <div class="vision-block">
                        <svg class="mini-icon simple-icon icon-mission" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
    </svg>
                        <div>
                            <h3>Mission</h3>
                            <p>
                                To provide an easy-to-use digital platform that improves waste management
                                efficiency and encourages sustainable recycling habits.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONTACT -->
        <section id="contact" class="snap-section recircle-section section-contact-wrap">
            <div class="contact-section-body">
                <h2 class="recircle-section-title">CONTACT US</h2>

                <?php if ($contact_message !== '') { ?>
                    <div class="contact-success"><?php echo htmlspecialchars($contact_message); ?></div>
                <?php } ?>

                <div class="contact-split">
                    <div class="contact-details">
                        <div class="contact-line">
                            <span class="contact-circle simple-icon icon-location" aria-hidden="true"></span>
                            <div>
                                <strong>Location:</strong><br>
                                SmartWaste Office | Local Council Waste Management Unit
                            </div>
                        </div>
                        <div class="contact-line">
                            <span class="contact-circle simple-icon icon-email" aria-hidden="true"></span>
                            <div>
                                <strong>Email:</strong><br>
                                waste.admin@local.gov.my
                            </div>
                        </div>
                        <div class="contact-line">
                            <span class="contact-circle simple-icon icon-phone" aria-hidden="true"></span>
                            <div>
                                <strong>Call:</strong><br>
                                03-8000 6000
                            </div>
                        </div>
                    </div>

                    <form class="contact-form" method="POST" action="index.php#contact">
                        <input type="text" name="your_name" placeholder="Your Name" required>
                        <input type="text" name="your_contact" placeholder="Your Contact" required>
                        <input type="email" name="your_email" placeholder="Your Email" required>
                        <textarea name="message" rows="5" placeholder="Message" required></textarea>
                        <button type="submit" name="send_contact" class="recircle-btn recircle-btn-wide">Send Message</button>
                    </form>
                </div>
            </div>

            <footer class="recircle-footer">
                <div class="recircle-footer-inner">
                    <p>© Copyright 2026 Smart Waste Collection Scheduler. All Rights Reserved.</p>
                    <div class="footer-links">
                        <a href="#home" class="nav-anchor">Home</a>
                        <a href="#about" class="nav-anchor">About</a>
                        <a href="login.php">Login</a>
                        <a href="register.php">Register</a>
                    </div>
                </div>
            </footer>
        </section>

    </main>

    <script src="js/landing.js"></script>
</body>
</html>
