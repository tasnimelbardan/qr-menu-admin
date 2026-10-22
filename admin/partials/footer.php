    </main>   <!-- closes .content -->
  </div>      <!-- closes .main -->
</div>        <!-- closes .admin-shell -->

<script>
  // Opens and closes the sidebar on small screens
  (function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('overlay');
    var button  = document.getElementById('menuToggle');

    function toggle() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    }

    button.addEventListener('click', toggle);
    overlay.addEventListener('click', toggle);
  })();
</script>
</body>
</html>