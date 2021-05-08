@extends('frontend.layouts.app')

@section('title', app_name() . ' | ' . __('navs.general.home'))

@section('content')
<div class="">
    Loading Stabstox
</div><!--row-->
<script type="text/javascript">
    location.href = '<?php echo env('VUE_URL'); ?>';
</script>
@endsection
