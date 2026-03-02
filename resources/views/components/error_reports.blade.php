 @if(session('success'))
 <div class="alert alert-light-success"
     role="alert">
     <div class="txt-success">
         {{ session('success') }}
     </div>
 </div>
 @endif
 @if(session('error'))
 <div class="alert alert-light-danger"
     role="alert">
     <div class="txt-danger"> {{ session('success') }}</div>
 </div>
 @endif

 @if($errors->any())
 <div class="alert alert-light-danger"
     role="alert">

     <ul class="txt-danger">
         @foreach($errors->all() as $error)
         <li>{{ $error }}</li>
         @endforeach
     </ul>
 </div>
 @endif