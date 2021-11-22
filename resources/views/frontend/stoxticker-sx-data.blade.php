@extends('frontend.layouts.app')

@section('title','SX Stoxticker')

@section('meta')
<meta name="title" content="SX Stoxticker">
<meta name="description" content="
<?php if(isset($items['basketball'][0])){ ?>
Basketball: <?php echo $items['basketball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['basketball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['soccer'][0])){ ?>
Soccer: <?php echo $items['soccer'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['soccer'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['football'][0])){ ?>
Football: <?php echo $items['football'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['football'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['baseball'][0])){ ?>
Baseball: <?php echo $items['baseball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['baseball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['pokemon'][0])){ ?>
Pokemon: <?php echo $items['pokemon'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['pokemon'][0]->card->id; ?>
<?php } ?>">

<meta  data-hid="card-og-site_name" property="og:site_name" content="Slabstox">
<meta  data-hid="card-og-type" property="og:type" content="article">
<meta  data-hid="card-og-type" property="fb:app_id" content="2791823984386463">

<meta  data-hid="card-og-url" property="og:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta  data-hid="card-og-title" property="og:title" content="SX Stoxticker">
<meta  data-hid="card-og-image" property="og:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta  data-hid="card-og-description" property="og:description" content="
<?php if(isset($items['basketball'][0])){ ?>
Basketball: <?php echo $items['basketball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['basketball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['soccer'][0])){ ?>
Soccer: <?php echo $items['soccer'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['soccer'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['football'][0])){ ?>
Football: <?php echo $items['football'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['football'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['baseball'][0])){ ?>
Baseball: <?php echo $items['baseball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['baseball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['pokemon'][0])){ ?>
Pokemon: <?php echo $items['pokemon'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['pokemon'][0]->card->id; ?>
<?php } ?>">

<meta  data-hid="card-article-published_time" property="article:published_time" content="<?php echo \Carbon\Carbon::parse($data['last_updated'])->timestamp; ?>">
<meta  data-hid="card-article-author" property="article:author" content="Slabstox">

<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta property="twitter:title" content="SX Stoxticker">
<meta property="twitter:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta property="twitter:description" content="
<?php if(isset($items['basketball'][0])){ ?>
Basketball: <?php echo $items['basketball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['basketball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['soccer'][0])){ ?>
Soccer: <?php echo $items['soccer'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['soccer'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['football'][0])){ ?>
Football: <?php echo $items['football'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['football'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['baseball'][0])){ ?>
Baseball: <?php echo $items['baseball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['baseball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['pokemon'][0])){ ?>
Pokemon: <?php echo $items['pokemon'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['pokemon'][0]->card->id; ?>
<?php } ?>">
<meta name="twitter:site" content="@Slabstox">
<meta name="twitter:image:alt" content="SX Stoxticker">
@endsection

@section('content')
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->
@endsection
