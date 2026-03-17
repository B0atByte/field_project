<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title><?= $page_title ?? 'Field Project' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if (file_exists(__DIR__ . '/../includes/csrf.php')) { require_once __DIR__ . '/../includes/csrf.php'; echo csrfMetaTag(); } ?>

  <!-- ✅ Favicon -->
  <link rel="icon" type="image/png" href="../image/BPL.png">

  <!-- ✅ Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">

  <!-- ✅ Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- ✅ Tailwind Custom Config -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            prompt: ['Prompt', 'sans-serif'],
          },
          colors: {
            primary: '#3B82F6',   // น้ำเงิน
            success: '#22C55E',   // เขียว
            warning: '#F59E0B',   // เหลือง/ส้ม
            danger:  '#EF4444',   // แดง
            purple:  '#8B5CF6',   // ม่วง
            sidebar: '#0F172A',   // สีเข้ม Sidebar
            surface: '#F8FAFC',   // พื้นหลังเนื้อหา
          },
          borderRadius: {
            xl: '0.8rem',
          }
        }
      }
    }
  </script>

  <!-- ✅ Material Symbols (Modern Icons) -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <style>
    .material-symbols-outlined {
      font-family: 'Material Symbols Outlined';
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      vertical-align: middle;
    }

    /* Reset เพื่อให้เต็มจอ */
    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
    }
  </style>

  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- DataTables + jQuery + SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-surface min-h-screen w-full font-prompt text-gray-900 flex flex-col">
