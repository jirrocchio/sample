<?php
session_start(); // Start the session

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>
    
    <link rel="stylesheet" href="jirro_style.css"> <!-- Link to external CSS file -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <!-- Navbar -->
    <nav>
        <div class="logo"><img src="images/logo.png" alt="OCSARLS Logo">OCSARLS </div>
        <ul class="nav-links">
          
        </ul>
        <a href="PHPS/search_student.php" class="login-butto" id="loginBt">Go to Dashboard</a>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <h1> <br> Olshco Comlab Smart Attendance & Room Lock System</h1>
        <div class="image-container">
            <img src="images/3b.jpg" alt="Group Image" class="main-image">
        </div>



        <div style="height: 120vh;"></div> <!-- This div is just to create scrolling space -->
    </div>

    
    <!-- Footer -->
    <footer>
        <!-- Team Members Section -->
        <div class="team-members">
            <div class="team-member">
                <img src="images/benjie.jpg" alt="Mark Benjie J. Balneg">
                <h3>Mark Benjie J. Balneg</h3>
                <p>Leader</p>
            </div>
            <div class="team-member">
                <img src="images/gian.jpg" alt="Roland Gian Lopez">
                <h3>Roland Gian Lopez</h3>
                <p>Honorable Member</p>
            </div>
            <div class="team-member">
                <img src="images/jirro.jpg" alt="Harold Jirro Madrona">
                <h3>Harold Jirro I. Madrona</h3>
                <p>Honorable Member</p>
            </div>
            <div class="team-member">
                <img src="images/king.jpg" alt="King Tristan Calderon">
                <h3>King Tristan Calderon</h3>
                <p>Honorable Member</p>
            </div>
        </div>

        <!-- Links Section -->
        <div class="footer-links">
            <h3>Quick Links:</h3>
            <ul>
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
        </div>

        <div class="footer-social">
            <h3>Follow Us on:</h3>
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
        </div>

        <div class="footer-bottom">
            &copy; 2024 OCSARLS. All rights reserved.
        </div>
    </footer>

    <!-- Link to external JavaScript file -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script src="js/jirro_script.js"></script>
    
</body>
</html>
