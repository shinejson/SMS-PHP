// Initialize DataTables
function initializeDataTables() {
    // Students Table
    if (document.getElementById('studentsTable') && !$.fn.DataTable.isDataTable('#studentsTable')) {
        $('#studentsTable').DataTable({
            responsive: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search students..."
            },
            columnDefs: [
                { orderable: false, targets: [6] } // Disable sorting for actions column
            ]
        });
    }

    // Teachers Table
    if (document.getElementById('teachersTable') && !$.fn.DataTable.isDataTable('#teachersTable')) {
        $('#teachersTable').DataTable({
            responsive: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search teachers..."
            }
        });
    }

    // Users Table
    if (document.getElementById('usersTable') && !$.fn.DataTable.isDataTable('#usersTable')) {
        $('#usersTable').DataTable({
            responsive: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search users..."
            },
            columnDefs: [
                { orderable: false, targets: [6] } // Disable sorting for actions column
            ]
        });
    }

    // Classes Table
    if (document.getElementById('classesTable') && !$.fn.DataTable.isDataTable('#classesTable')) {
        $('#classesTable').DataTable({
            responsive: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search classes..."
            }
        });
    }

    // Marks Table
    if (document.getElementById('marksTable') && !$.fn.DataTable.isDataTable('#marksTable')) {
        $('#marksTable').DataTable({
            responsive: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search marks..."
            }
        });
    }

    // Payments Table
    if (document.getElementById('paymentsTable') && !$.fn.DataTable.isDataTable('#paymentsTable')) {
        $('#paymentsTable').DataTable({
            responsive: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search payments..."
            },
            columnDefs: [
                { 
                    render: function(data, type, row) {
                        return type === 'display' ?
                            '<span class="currency">' + data + '</span>' :
                            data;
                    },
                    targets: [5] // Format currency column
                }
            ]
        });
    }

    // Format currency values
    $('.currency').each(function() {
        const value = $(this).text();
        if (value && !isNaN(parseFloat(value))) {
            $(this).text('$' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
        }
    });
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeDataTables();
});