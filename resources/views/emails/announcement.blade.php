Hello,

<p>A new announcement has been posted:</p>

<h2>{{ $announcement->title }}</h2>

<p>{!! nl2br(e($announcement->body)) !!}</p>

<p><small>Posted by: {{ $announcement->user->name }} on {{ $announcement->created_at->format('d M, Y') }}</small></p>

<p>Thanks,<br>{{ config('app.name') }}</p>
