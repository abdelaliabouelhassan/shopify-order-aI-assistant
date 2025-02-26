

    <h1>Ask About All Orders</h1>
    
    <form action="{{ route('ask') }}" method="POST">
        @csrf
        <div class="form-group">
            <textarea name="question" 
                      class="form-control" 
                      placeholder="Ask about all orders..."
                      rows="5"></textarea>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Ask AI</button>
    </form>
    
    <div class="mt-4">
        <h4>Example Questions:</h4>
        <ul>
            <li>"What's the total revenue of all orders?"</li>
            <li>"Which products are most frequently ordered?"</li>
            <li>"Show me customer demographics analysis"</li>
        </ul>
    </div>
