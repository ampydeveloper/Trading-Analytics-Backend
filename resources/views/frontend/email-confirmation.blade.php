@extends('frontend.layouts.app')

@section('title', app_name() . ' | ' . __('navs.general.home'))

@push('after-styles')
<style type="text/css">
    .navbar {
        display: none;
    }
    .outer-green{
        background: #1ce783;
        padding: 70px 0;
    }
    .align-cen{
        margin: 0 auto;
    }
    .inner-white{
        background: #fff;
        padding: 50px;
        text-align: center;
    }
    .message-outer{
        margin: 0;
        line-height: 4;
        font-weight: bolder;
        font-size: 16px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
</style>
@endpush

@section('content')
<div class="row outer-green">
    <div class="col-sm-10 align-cen">
        <div class="inner-white">
            <div class="message-outer ">
                <?php
                echo $message;
                ?>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    setTimeout(function () {
        location.href = '<?php echo env('VUE_URL'); ?>';
    }, 5000);
</script>
@endsection
