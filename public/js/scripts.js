function updateQuantity(productId, change) {
    $.ajax({
        url: 'update_cart.php',
        method: 'POST',
        data: {
            product_id: productId,
            change: change
        },
        success: function(response) {
            if (response.success) {
                $('#qty' + productId).val(response.quantity);
                updateOrderSummary();
            } else {
                alert('Error updating cart: ' + response.message);
            }
        },
        error: function() {
            alert('Error communicating with server');
        }
    });
}

function updateOrderSummary() {
    $.ajax({
        url: 'get_order_summary.php',
        method: 'GET',
        success: function(response) {
            $('#order-summary').html(response);
        },
        error: function() {
            alert('Error updating order summary');
        }
    });
}