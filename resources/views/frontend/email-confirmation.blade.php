@extends('frontend.layouts.app')

@section('title', app_name() . ' | ' . __('navs.general.home'))

@section('content')
<div class="">
    <div class="message-outer alert alert-success">
        <?php
        echo $message;
        ?>
    </div>
</div><!--row-->
<script type="text/javascript">
    setTimeout(function () {
        location.href = '<?php echo env('VUE_URL'); ?>';
    }, 5000);
</script>
@endsection
