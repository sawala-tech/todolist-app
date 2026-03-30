<?php
require_once __DIR__ . '../../../../assets/helpers/libs.php';
?>

</footer>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<?php $scriptVersion = @filemtime(__DIR__ . '/../../../assets/js/script.js') ?: time(); ?>
<script src="<?= assets('js/script.js') . '?v=' . $scriptVersion ?>"></script>
</body>


</html>