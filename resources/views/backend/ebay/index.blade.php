@extends('backend.layouts.app')

@section('title', 'Ebay | Listing')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-sm-5">
                <h4 class="card-title mb-0">
                    Ebay List
                </h4>
            </div><!--col-->

            <div class="col-sm-7">

            </div><!--col-->
        </div><!--row-->

        <div class="row mt-4">
            <div class="col">
                <div class="table-responsive">
                    <table class="table" id="ebayitemslist">
                        <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Item Id</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div><!--col-->
        </div><!--row-->
    </div><!--card-body-->
</div><!--card-->
@endsection

@push('after-scripts')
<script>
    $(function(){
        window.ebayitemslistDatatable = $('#ebayitemslist').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "{{route('admin.ebay.datatable.list')}}",
                "type": "POST",
                "headers": {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            },
            "columns": [
                { "data": "image" },
                { "data": "title" },
                { "data": "itemId" },
                { "data": "action" }
            ]
        })

        $(document).on('click','.delete-card',function(){
            var url = $(this).attr('data-url');
            Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        type: 'delete',
                        beforeSend: function(request) {
                            request.setRequestHeader('X-CSRF-TOKEN', $('meta[name="csrf-token"]').attr('content'));
                        },
                        success: function() {
                            Swal.fire(
                            'Deleted!',
                            'Card has been deleted.',
                            'success'
                            );
                            window.ebayitemslistDatatable.draw('page');
                        },
                        error: function() {
                            Swal.fire(
                            'Failed!',
                            'Unabled to process your request.',
                            'warning'
                            );
                        }
                    })
                }
            })
        })
    });


</script>
@endpush
