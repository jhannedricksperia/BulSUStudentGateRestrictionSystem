<?php

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSU Gate System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #870000; 
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: white;
        }

        .container {
            z-index: 1;
            text-align: center;
            max-width: 850px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .logo {
            width: 100px;
            height: auto;
            border-radius: 50%;
        }

        .university-name {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h1 {
            margin: 10px 0;
            font-size: 2rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .info-text {
            font-size: 1rem;
            color: #fff;
            opacity: 0.9;
            margin-bottom: 40px;
        }

        .cards {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .card {
            background: white;
            color: #333;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            width: 200px;
            padding: 30px 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s, background 0.3s, color 0.3s;
            cursor: pointer;
            text-decoration: none;
            border-top: 5px solid #ffcc00;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.3);
            background: #ffcc00;
            color: #000;
        }

        .card i {
            font-size: 50px;
            color: #870000;
            margin-bottom: 15px;
            transition: color 0.3s;
        }

        .card:hover i {
            color: #870000;
        }

        .card h3 {
            margin: 10px 0 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        footer {
            margin-top: 50px;
            font-size: 0.9rem;
            opacity: 0.8;
        }
               #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
        }
        
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div id="particles-js"></div>
    <div class="container">
        <div class="header">
            <img src="BSUU.webp" alt="BSU Logo" class="logo">
            <div class="university-name">Bulacan State University</div>
        </div>

        <h1>Student Gate Restriction System</h1>
        <p class="info-text">
            Welcome to the Bulacan State University Gate Management System. <br>
            Select your role below to access your respective dashboard.
        </p>

        <div class="cards">
            <a href="adminLogin.php" class="card">
                <i class="fas fa-user-shield"></i>
                <h3>Admin</h3>
            </a>

            <a href="staffLogin.php" class="card">
                <i class="fas fa-users"></i>
                <h3>Staff</h3>
            </a>

            <a href="guardLogin.php" class="card">
                <i class="fas fa-user-lock"></i>
                <h3>Guard</h3>
            </a>
        </div>

        <footer>
            &copy; <?php echo date("Y"); ?> Bulacan State University - Student Gate Restriction System
        </footer>
    </div>
       <script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // Particles.js configuration
        particlesJS('particles-js', {
            particles: {
                number: {
                    value: 80,
                    density: {
                        enable: true,
                        value_area: 800
                    }
                },
                color: {
                    value: '#ffffff'
                },
                shape: {
                    type: 'circle'
                },
                opacity: {
                    value: 0.5,
                    random: false,
                    anim: {
                        enable: false
                    }
                },
                size: {
                    value: 3,
                    random: true,
                    anim: {
                        enable: false
                    }
                },
                line_linked: {
                    enable: true,
                    distance: 150,
                    color: '#ffffff',
                    opacity: 0.4,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 2,
                    direction: 'none',
                    random: false,
                    straight: false,
                    out_mode: 'out',
                    bounce: false
                }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: {
                        enable: true,
                        mode: 'grab'
                    },
                    onclick: {
                        enable: true,
                        mode: 'push'
                    },
                    resize: true
                },
                modes: {
                    grab: {
                        distance: 140,
                        line_linked: {
                            opacity: 1
                        }
                    },
                    push: {
                        particles_nb: 4
                    }
                }
            },
            retina_detect: true
        });

    </script>
</body>
</html>