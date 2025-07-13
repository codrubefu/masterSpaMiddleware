<!DOCTYPE html>
<html>
<head>
    <title>Room Search Test</title>
</head>
<body>
    <h1>Test Room Search API</h1>
    <form method="POST" action="{{ route('room-search-test.submit') }}">
        @csrf
        <label>Adults: <input type="number" name="adults" value="{{ old('adults', 2) }}"></label><br>
        <label>Kids: <input type="number" name="kids" value="{{ old('kids', 0) }}"></label><br>
        <label>Number of Rooms: <input type="number" name="number_of_rooms" value="{{ old('number_of_rooms', 1) }}"></label><br>
        <label>Start Date: <input type="date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}"></label><br>
        <label>End Date: <input type="date" name="end_date" value="{{ old('end_date', date('Y-m-d', strtotime('+1 day'))) }}"></label><br>
        <button type="submit">Search</button>
    </form>

    @if(isset($result))
        <h2>Result</h2>
        <pre>{{ json_encode($result, JSON_PRETTY_PRINT) }}</pre>
    @endif
</body>
</html>
