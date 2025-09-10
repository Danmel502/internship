<?php
$current_page = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Media Track</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="bg-light">

    <?php include 'views/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero py-5 text-center text-white">
        <div class="container">
            <h1 class="fw-bold display-6">A Global Content Aggregation <br>& Conversion Company</h1>
            <p class="lead mt-3">
                We transform media content into structured, searchable, and actionable data.<br>
                Our solutions are customizable, scalable, and fast—without compromising accuracy or security.
            </p>
        </div>
    </section>

    <div class="divider-line"></div>

    <!-- About Content -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="content-box p-4 p-md-5">
                <p class="fs-5 text-center">
                    With a team of over <strong>500+</strong> dedicated staff and secure cloud-based setup, we are able to deliver around the clock, <strong>365 days a year</strong> with speed and accuracy across five continents.
                </p>
                <p class="text-muted text-center">
                    With more than a decade of industry-leading expertise and a secure cloud-based setup, we focus on improving and creating new, exciting, and relevant products for our clients with cutting-edge technology.
                </p>

                <div class="row text-center mt-4">
                    <div class="col-md-2 col-sm-6 mb-4">
                        <h4 class="fw-bold">500+</h4>
                        <p class="mb-0 small text-muted">Dedicated Staff</p>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-4">
                        <h4 class="fw-bold">2009</h4>
                        <p class="mb-0 small text-muted">Founding Year</p>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-4">
                        <h4 class="fw-bold">2</h4>
                        <p class="mb-0 small text-muted">Founders</p>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <h5 class="fw-bold">Sustainably<br>Run Business</h5>
                    </div>
                    <div class="col-md-3 col-sm-12 mb-4">
                        <h6 class="fw-bold">Nordic roots + Asia based = Global</h6>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>