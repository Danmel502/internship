<!DOCTYPE html>
<html>
<head>
    <title>About Us - Features Documentation Tool</title>
    <!-- Bootstrap + Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        #about h2 {
            font-size: 2rem;
        }
        .divider-line {
            height: 20px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="20"><path fill="%23ffffff" d="M0 0 C50 30 100 0 150 30 C200 60 250 0 300 30 L300 0 Z"></path></svg>') repeat-x;
            background-size: cover;
        }
    </style>
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand text-success" href="index.php">Media <span class="text-dark">Track</span></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero py-5 text-center text-white" style="background-color: #037b3d;">
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
<section id="about" class="py-5 bg-white">
  <div class="container">
    <div class="bg-light p-4 p-md-5 rounded shadow-sm">
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

</body>
</html>
