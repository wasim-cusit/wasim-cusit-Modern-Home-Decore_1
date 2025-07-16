<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
  // Initialize all Bootstrap dropdowns
  $('.dropdown-toggle').dropdown();
  
  // Highlight active dropdown parent when child is active
  $('.dropdown-item.active').each(function() {
    $(this).closest('.dropdown').find('.dropdown-toggle').addClass('active');
  });

  // Load dashboard stats when on the dashboard page
  if (window.location.search.includes('page=welcome') || window.location.pathname.endsWith('/')) {
    loadDashboardStats();
    // Refresh stats every 60 seconds
    setInterval(loadDashboardStats, 60000);
  }
  
  // Add active class to dropdown items
  $('.dropdown-item').each(function() {
    if (window.location.search.includes($(this).attr('href').split('=')[1])) {
      $(this).addClass('active');
    }
  });
});

function loadDashboardStats() {
  // Show loading state
  $('.stat-card h2').html('<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>');
  
  $.ajax({
    url: 'ajax_get_summary.php',
    type: 'GET',
    dataType: 'json',
    success: function(data) {
      if (data.status === 'success') {
        // Update the counts
        $('#quotationCount').text(data.quotations);
        $('#companyCount').text(data.companies);
        $('#expenseTotal').html('<span class="text-rupee">Rs</span>' + data.expenses_total);
        $('#clientCount').text(data.clients);
        
        // Update the timestamp
        const now = new Date();
        $('.stat-card small').text('Updated at ' + now.toLocaleTimeString());
      } else {
        showDashboardError(data.message || 'Unknown error occurred');
      }
    },
    error: function(xhr, status, error) {
      showDashboardError('Failed to connect to server');
    }
  });
}

function showDashboardError(message) {
  $('.stat-card h2').html('<span class="text-danger">Error</span>');
  $('.stat-card small').text(message);
  console.error('Dashboard error:', message);
}
</script>