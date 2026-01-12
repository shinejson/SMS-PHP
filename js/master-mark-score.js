let masterMarksTable = null;

$(document).ready(function() {
    initMasterMarksTable();
});

function initMasterMarksTable() {
    // Show loading state
    $('#masterMarksTable tbody').html('<tr><td colspan="15" class="text-center">Loading data...</td></tr>');
    
    // Fetch data from server
    fetch('get_master_marks.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Check if we got an error response
            if (data.error) {
                $('#masterMarksTable tbody').html('<tr><td colspan="15" class="text-center">Error: ' + data.error + '</td></tr>');
                return;
            }
            
            if (data.length === 0) {
                $('#masterMarksTable tbody').html('<tr><td colspan="15" class="text-center">No data available</td></tr>');
                return;
            }
            
            console.log('Loaded ' + data.length + ' records');
            console.log('First record:', data[0]);
            console.log('Available fields:', Object.keys(data[0]));
            
            // Check if rank and grade exist in the data
            if (data[0].hasOwnProperty('rank')) {
                console.log('✓ Rank field exists');
            } else {
                console.log('✗ Rank field is missing');
            }
            
            if (data[0].hasOwnProperty('grade')) {
                console.log('✓ Grade field exists');
            } else {
                console.log('✗ Grade field is missing');
            }
            
            // Initialize DataTable
            masterMarksTable = $('#masterMarksTable').DataTable({
                data: data,
                destroy: true, // Allow reinitialization
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'excel',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'pdf',
                        exportOptions: {
                            columns: ':visible'
                        },
                        orientation: 'landscape',
                        pageSize: 'A3'
                    },
                    {
                        extend: 'print',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[11, 'desc']], // Order by final_grade column (index 11)
                columns: [
                    { 
                        data: 'student_name',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                return `<a href="report_sheet.php?student_id=${row.student_id}&term_id=${row.term_id}&academic_year_id=${row.academic_year_id}" 
                                           class="student-link" target="_blank">
                                           ${data} <i class="fas fa-external-link-alt"></i>
                                        </a>`;
                            }
                            return data;
                        }
                    },
                    { data: 'class_name' },
                    { data: 'subject_name' },
                    { data: 'term' },
                    { data: 'academic_year' },
                    { 
                        data: 'midterm_total',
                        render: function(data, type, row) {
                            return type === 'display' ? parseFloat(data).toFixed(2) : data;
                        }
                    },
                    { 
                        data: 'midterm_weighted',
                        render: function(data, type, row) {
                            return type === 'display' ? parseFloat(data).toFixed(2) : data;
                        }
                    },
                    { 
                        data: 'class_score_total',
                        render: function(data, type, row) {
                            return type === 'display' ? parseFloat(data).toFixed(2) : data;
                        }
                    },
                    { 
                        data: 'class_score_weighted',
                        render: function(data, type, row) {
                            return type === 'display' ? parseFloat(data).toFixed(2) : data;
                        }
                    },
                    { 
                        data: 'exam_score_total',
                        render: function(data, type, row) {
                            return type === 'display' ? parseFloat(data).toFixed(2) : data;
                        }
                    },
                    { 
                        data: 'exam_score_weighted',
                        render: function(data, type, row) {
                            return type === 'display' ? parseFloat(data).toFixed(2) : data;
                        }
                    },
                    { 
                        data: 'final_grade',
                        render: function(data, type, row) {
                            return type === 'display' ? parseFloat(data).toFixed(2) : data;
                        }
                    },
{
    data: 'rank',
    render: function(data, type, row) {
        if (type === 'sort' || type === 'filter' || type === 'type') {
            return data;
        }

        if (type === 'display') {
            if (data === null || data === undefined || data === '') {
                return '<span class="badge badge-secondary">-</span>';
            }

            const rank = parseInt(data);
            if (isNaN(rank)) {
                return '<span class="badge badge-secondary">-</span>';
            }

            let badgeClass = 'badge-secondary';
            let crownIcon = '';

            if (rank === 1) {
                badgeClass = 'badge-gold';
                crownIcon = '👑';
            } else if (rank === 2) {
                badgeClass = 'badge-silver';
                crownIcon = '👑';
            } else if (rank === 3) {
                badgeClass = 'badge-bronze';
                crownIcon = '👑';
            }

            return `<span class="badgee ${badgeClass}">${crownIcon} ${rank}</span>`;
        }

        return data;
    }
},

{
    data: 'grade',
    render: function(data, type, row) {
        if (type === 'sort' || type === 'filter' || type === 'type') {
            return data;
        }
        
        if (type === 'display') {
            if (!data || String(data).trim() === '') {
                return '<span class="badge badge-secondary">-</span>';
            }
            
            const grade = String(data).trim();
            let color = 'secondary';
            
            switch(grade.toUpperCase()) {
                case 'A': color = 'success'; break;
                case 'B': color = 'primary'; break;
                case 'C': color = 'info'; break;
                case 'D': color = 'warning'; break;
                case 'F': color = 'danger'; break;
            }
            
            return '<span class="badgee badge-' + color + '">' + grade + '</span>';
        }
        
        return data;
    }
},
                    { 
                        data: 'remark',
                        render: function(data, type, row) {
                            return data || '-';
                        }
                    }
                ],
                columnDefs: [
                    { className: 'text-center', targets: [5, 6, 7, 8, 9, 10, 11, 12, 13] },
                    { orderable: true, targets: '_all' }
                ],
                initComplete: function() {
                    // Add custom filtering
                    setupMasterTableFilters();
                    console.log('DataTable initialized successfully');
                }
            });
        })
        .catch(error => {
            console.error('Error loading master marks data:', error);
            $('#masterMarksTable tbody').html('<tr><td colspan="15" class="text-center">Error loading data. Please check the console for details.</td></tr>');
        });
}

function setupMasterTableFilters() {
    // Class filter (column 1)
    $('#classFilterMaster').off('change').on('change', function() {
        masterMarksTable.column(1).search(this.value).draw();
    });
    
    // Term filter (column 3)
    $('#termFilterMaster').off('change').on('change', function() {
        masterMarksTable.column(3).search(this.value).draw();
    });
    
    // Academic Year filter (column 4)
    $('#yearFilterMaster').off('change').on('change', function() {
        masterMarksTable.column(4).search(this.value).draw();
    });
    
    // Student filter (column 0)
    $('#studentFilterMaster').off('change').on('change', function() {
        masterMarksTable.column(0).search(this.value).draw();
    });
    
    // Clear filters
    $('#clearFiltersBtnMaster').off('click').on('click', function() {
        $('#classFilterMaster, #termFilterMaster, #yearFilterMaster, #studentFilterMaster').val('');
        masterMarksTable.columns().search('').draw();
    });
}