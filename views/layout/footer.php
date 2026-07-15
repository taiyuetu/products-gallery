</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alerts after 4 s
document.querySelectorAll('.alert-dismissible').forEach(el => {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 4000);
});

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', e => {
    if (!confirm(btn.dataset.confirm)) e.preventDefault();
  });
});
</script>
</body>
</html>
