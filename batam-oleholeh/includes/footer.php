<?php
// includes/footer.php
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Oleh-oleh Batam<?php echo (strpos($currentPage, 'admin') !== false) ? ' Admin Panel' : ''; ?>. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>