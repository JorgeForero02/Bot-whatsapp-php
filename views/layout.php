<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'WhatsApp Bot'; ?></title>
    <script>
        const BASE_PATH = '<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>';
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#075E54',
                        secondary: '#128C7E',
                        accent: '#1eb854',
                        dark: '#1F2937',
                        'whatsapp-bg': '#ECE5DD',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .message-bubble {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .chat-container {
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxkZWZzPjxwYXR0ZXJuIGlkPSJhIiBwYXR0ZXJuVW5pdHM9InVzZXJTcGFjZU9uVXNlIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiPjxwYXRoIGQ9Ik0wIDBoNDB2NDBIMHoiIGZpbGw9IiNmOWZhZmIiLz48cGF0aCBkPSJNMCAyMGg0MHYxSDB6TTIwIDBoMXY0MGgtMXoiIGZpbGw9IiNlNWU3ZWIiLz48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjYSkiLz48L3N2Zz4=');
        }
        
        .dark .chat-container {
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxkZWZzPjxwYXR0ZXJuIGlkPSJhIiBwYXR0ZXJuVW5pdHM9InVzZXJTcGFjZU9uVXNlIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiPjxwYXRoIGQ9Ik0wIDBoNDB2NDBIMHoiIGZpbGw9IiMxMTExMjciLz48cGF0aCBkPSJNMCAyMGg0MHYxSDB6TTIwIDBoMXY0MGgtMXoiIGZpbGw9IiMxZjFmMmUiLz48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjYSkiLz48L3N2Zz4=');
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors">
    <nav class="bg-primary dark:bg-gray-800 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-xl font-bold text-white">WhatsApp Bot</h1>
                        <p class="text-xs text-gray-200">Panel de Administración</p>
                    </div>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <div class="flex items-baseline space-x-4">
                        <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/" class="<?php echo ($currentPage ?? '') === 'dashboard' ? 'bg-secondary' : 'hover:bg-secondary hover:bg-opacity-75'; ?> text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                            Dashboard
                        </a>
                        <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/conversations" class="<?php echo ($currentPage ?? '') === 'conversations' ? 'bg-secondary' : 'hover:bg-secondary hover:bg-opacity-75'; ?> text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                            Conversaciones
                        </a>
                        <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/documents" class="<?php echo ($currentPage ?? '') === 'documents' ? 'bg-secondary' : 'hover:bg-secondary hover:bg-opacity-75'; ?> text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                            Documentos
                        </a>
                        <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/settings" class="<?php echo ($currentPage ?? '') === 'settings' ? 'bg-secondary' : 'hover:bg-secondary hover:bg-opacity-75'; ?> text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                            Configuración
                        </a>
                    </div>
                    
                    <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-white bg-opacity-20 hover:bg-opacity-30 text-white transition-all" title="Cambiar modo">
                        <svg id="theme-icon-light" class="hidden w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg id="theme-icon-dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="md:hidden flex items-center space-x-2">
                    <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-white bg-opacity-20 hover:bg-opacity-30 text-white transition-all">
                        <svg id="theme-icon-light-mobile" class="hidden w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg id="theme-icon-dark-mobile" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                    <button id="mobile-menu-button" class="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <div id="mobile-menu" class="hidden md:hidden bg-secondary dark:bg-gray-700">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/" class="<?php echo ($currentPage ?? '') === 'dashboard' ? 'bg-primary' : ''; ?> text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary transition-all">
                    Dashboard
                </a>
                <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/conversations" class="<?php echo ($currentPage ?? '') === 'conversations' ? 'bg-primary' : ''; ?> text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary transition-all">
                    Conversaciones
                </a>
                <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/documents" class="<?php echo ($currentPage ?? '') === 'documents' ? 'bg-primary' : ''; ?> text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary transition-all">
                    Documentos
                </a>
                <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/settings" class="<?php echo ($currentPage ?? '') === 'settings' ? 'bg-primary' : ''; ?> text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary transition-all">
                    Configuración
                </a>
            </div>
        </div>
    </nav>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 transition-colors">
        <?php echo $content ?? ''; ?>
    </main>
    
    <script>
        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            
            if (isDark) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                document.getElementById('theme-icon-light')?.classList.remove('hidden');
                document.getElementById('theme-icon-dark')?.classList.add('hidden');
                document.getElementById('theme-icon-light-mobile')?.classList.remove('hidden');
                document.getElementById('theme-icon-dark-mobile')?.classList.add('hidden');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                document.getElementById('theme-icon-light')?.classList.add('hidden');
                document.getElementById('theme-icon-dark')?.classList.remove('hidden');
                document.getElementById('theme-icon-light-mobile')?.classList.add('hidden');
                document.getElementById('theme-icon-dark-mobile')?.classList.remove('hidden');
            }
        }
        
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            document.getElementById('theme-icon-light')?.classList.add('hidden');
            document.getElementById('theme-icon-dark')?.classList.remove('hidden');
            document.getElementById('theme-icon-light-mobile')?.classList.add('hidden');
            document.getElementById('theme-icon-dark-mobile')?.classList.remove('hidden');
        } else {
            document.getElementById('theme-icon-light')?.classList.remove('hidden');
            document.getElementById('theme-icon-dark')?.classList.add('hidden');
            document.getElementById('theme-icon-light-mobile')?.classList.remove('hidden');
            document.getElementById('theme-icon-dark-mobile')?.classList.add('hidden');
        }
        
        document.getElementById('mobile-menu-button')?.addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
        
        <?php echo $scripts ?? ''; ?>
    </script>
</body>
</html>
