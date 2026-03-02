<?php $__env->startSection('title', 'Page Not Found'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mt-5">
    <div class="text-center">
        <h1 class="display-4 text-danger">404</h1>
        <p class="lead">Oops! The page you are looking for does not exist.</p>
        <a href="<?php echo e(route('dashboard')); ?>" class="btn btn-primary">Go to Dashboard</a>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\CHIPS\chips_2025\resources\views/errors/404.blade.php ENDPATH**/ ?>