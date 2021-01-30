@extends('backend.layouts.app')

@section('title', 'Cards | Import')

@section('content')

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-sm-5">
                    <h4 class="card-title mb-0">
                        Upload Card Excel
                    </h4>
                </div><!--col-->
            </div><!--row-->

            <hr>
            {{ html()->form('POST', route('admin.card.excelUpload'))->class('form-horizontal')->attribute('enctype', 'multipart/form-data')->open() }}
            <div class="row mt-4">
                <div class="col">
                    <div class="form-group row">
                        
                        {{ html()->label('Upload Baseball Card excel')
                            ->class('col-md-2 form-control-label')
                            ->for('baseball_excel') }}

                        <div class="col-md-9">
                            {{ html()->file('baseball_excel')
                                ->class('form-control')
                                ->required()
                                ->autofocus() }}
                        </div><!--col-->
                        <div class="col-md-1">
                        {{ form_submit('Upload') }}
                        </div>
                    </div><!--form-group-->
                    
                </div><!--col-->
            </div><!--row-->
            {{ html()->form()->close() }}
            {{ html()->form('POST', route('admin.card.excelUpload'))->class('form-horizontal')->attribute('enctype', 'multipart/form-data')->open() }}
            <div class="row mt-4">
                <div class="col">
                    <div class="form-group row">
                        {{ html()->label('Upload Basketball Card excel')
                            ->class('col-md-2 form-control-label')
                            ->for('basketball_excel') }}

                        <div class="col-md-9">
                            {{ html()->file('basketball_excel')
                                ->class('form-control')
                                ->required()
                                ->autofocus() }}
                        </div><!--col-->
                        <div class="col-md-1">
                        {{ form_submit('Upload') }}
                        </div>
                    </div><!--form-group-->

                </div><!--col-->
            </div><!--row-->
            {{ html()->form()->close() }}
            {{ html()->form('POST', route('admin.card.excelUpload'))->class('form-horizontal')->attribute('enctype', 'multipart/form-data')->open() }}
            <div class="row mt-4">
                <div class="col">
                    <div class="form-group row">
                        {{ html()->label('Upload Football Card excel')
                            ->class('col-md-2 form-control-label')
                            ->for('football_excel') }}

                        <div class="col-md-9">
                            {{ html()->file('football_excel')
                                ->class('form-control')
                                ->required()
                                ->autofocus() }}
                        </div><!--col-->
                        <div class="col-md-1">
                        {{ form_submit('Upload') }}
                        </div>
                    </div><!--form-group-->

                </div><!--col-->
            </div><!--row-->
            {{ html()->form()->close() }}
            {{ html()->form('POST', route('admin.card.excelUpload'))->class('form-horizontal')->attribute('enctype', 'multipart/form-data')->open() }}
            <div class="row mt-4">
                <div class="col">
                    <div class="form-group row">
                        {{ html()->label('Upload Soccer Card excel')
                            ->class('col-md-2 form-control-label')
                            ->for('soccer_excel') }}

                        <div class="col-md-9">
                            {{ html()->file('soccer_excel')
                                ->class('form-control')
                                ->required()
                                ->autofocus() }}
                        </div><!--col-->
                        <div class="col-md-1">
                        {{ form_submit('Upload') }}
                        </div>
                    </div><!--form-group-->

                </div><!--col-->
            </div><!--row-->
            {{ html()->form()->close() }}
        </div><!--card-body-->

        <div class="card-footer">
            <div class="row">
               <div class="col">
                    {{ form_cancel(route('admin.card.index'), __('buttons.general.cancel')) }}
                </div><!--col-->

                <div class="col text-right">
                    
                </div><!--col-->
            </div><!--row-->
        </div><!--card-footer-->
    </div><!--card-->

@endsection
