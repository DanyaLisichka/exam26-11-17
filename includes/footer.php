<footer class="site-footer py-3 px-3 my-4">
    <div class="row g-2 align-items-center">
        <div class="col-md-4">г. Москва, ул. Ивовая, 48</div>
        <div class="col-md-4 text-md-center">Ежедневно, круглосуточно</div>
        <div class="col-md-4 text-md-end"><a href="tel:88005553535">8 (800) 555-35-35</a></div>
    </div>
    <div class="small text-secondary mt-2">© <?= date('Y') ?> Гостиница «Сладкие сны»</div>
</footer>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery-3.7.1.slim.min.js"></script>
<script src="js/jquery.inputmask.min.js"></script>
<script src="js/index.js"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
