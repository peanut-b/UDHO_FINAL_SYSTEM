<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>404 Not Found</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .gradient-bg {
      background: linear-gradient(135deg, #6b73ff 0%, #000dff 100%);
    }
  </style>
</head>
<body class="gradient-bg h-screen flex items-center justify-center text-white font-sans">
  <div class="container text-center px-4">
    <div class="backdrop-blur-md bg-white/10 p-10 rounded-3xl shadow-2xl max-w-2xl mx-auto border border-white/20">
      <!-- Emoji-style SVG -->
      <svg class="mx-auto mb-6 w-28 h-28 text-white/80" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 10h.01"></path>
        <path d="M15 10h.01"></path>
        <path d="M9.5 15.5c1.38 1.5 3.62 1.5 5 0"></path>
        <circle cx="12" cy="12" r="10"></circle>
      </svg>

      <h1 class="text-8xl font-bold mb-4 text-white">404</h1>
      <p class="text-2xl mb-8 text-white/90">Oops! Page not found.</p>
      <p class="mb-8 text-white/70">The page you're looking for doesn't exist or has been moved.</p>
      
      <!-- Home button with cool hover effect -->
      <a href="\UDHO%20SYSTEM\Operation\operation_dashboard.php" class="
        inline-block px-8 py-3 rounded-full bg-white text-blue-600 font-semibold 
        shadow-lg transition-all duration-300 
        hover:scale-105 hover:shadow-xl hover:bg-blue-50
        focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600
      ">
        Go Back Home
      </a>

      <!-- Optional search bar -->
      <div class="mt-10 max-w-md mx-auto">
        <p class="mb-3 text-white/60">Or try searching:</p>
        <div class="flex">
          <input type="text" placeholder="Search..." class="
            flex-grow px-4 py-2 rounded-l-full focus:outline-none
            text-gray-800
          ">
          <button class="
            bg-blue-500 text-white px-6 py-2 rounded-r-full
            hover:bg-blue-600 transition
          ">
            Search
          </button>
        </div>
      </div>
    </div>
  </div>
</body>
</html>