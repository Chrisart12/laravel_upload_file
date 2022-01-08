@extends('../layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="panel panel-default">
                <div class="panel-heading">Dashboard</div>

                <div class="panel-body">
                 <form action="upload" enctype="multipart/form-data" method="post">
                {!! csrf_field() !!}  
                    <div class="form-group">
                        <label for="photo">Télécharger une image</label>
                        <input type="file" name="photo">
                    </div>
                    <button type="submit" class="btn btn-primary">ENVOYER</button>
                 </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
