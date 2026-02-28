/**
 * Custom JavaScript for specific features
 */

// ===== DASHBOARD CHARTS =====
function initDashboardCharts() {
    // Attendance Chart
    if (document.getElementById('attendanceChart')) {
        var ctx1 = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Present',
                    data: [65, 72, 68, 75, 70, 55, 48],
                    borderColor: '#2a5298',
                    backgroundColor: 'rgba(42, 82, 152, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Absent',
                    data: [12, 8, 10, 7, 9, 15, 18],
                    borderColor: '#d32f2f',
                    backgroundColor: 'rgba(211, 47, 47, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Fee Chart
    if (document.getElementById('feeChart')) {
        var ctx2 = document.getElementById('feeChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Collected', 'Pending', 'Overdue'],
                datasets: [{
                    data: [75, 15, 10],
                    backgroundColor: ['#388e3c', '#f57c00', '#d32f2f'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// ===== COUNTER ANIMATION =====
function initCounters() {
    $('.counter').each(function() {
        var $this = $(this);
        var target = parseInt($this.data('target'));
        
        $({ count: 0 }).animate({ count: target }, {
            duration: 2000,
            easing: 'swing',
            step: function() {
                $this.text(Math.floor(this.count));
            },
            complete: function() {
                $this.text(this.count);
            }
        });
    });
}

// ===== STUDENT SEARCH =====
function initStudentSearch() {
    $('#studentSearch').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#studentsTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
}

// ===== MARKS ENTRY CALCULATION =====
function calculateGrade(marks) {
    if (marks >= 80) return { grade: 'A+', point: 5.00 };
    else if (marks >= 70) return { grade: 'A', point: 4.00 };
    else if (marks >= 60) return { grade: 'A-', point: 3.50 };
    else if (marks >= 50) return { grade: 'B', point: 3.00 };
    else if (marks >= 40) return { grade: 'C', point: 2.00 };
    else if (marks >= 33) return { grade: 'D', point: 1.00 };
    else return { grade: 'F', point: 0.00 };
}

// ===== ATTENDANCE MARKING =====
function markAllPresent() {
    $('input[value="Present"]').prop('checked', true);
}

function markAllAbsent() {
    $('input[value="Absent"]').prop('checked', true);
}

// ===== SMS CHARACTER COUNTER =====
function countSMSCharacters(textareaId, counterId) {
    var message = $(textareaId).val();
    var length = message.length;
    var smsCount = Math.ceil(length / 160);
    
    $(counterId).text(length + '/1000 characters (' + smsCount + ' SMS)');
    
    if (length > 900) {
        $(counterId).addClass('text-danger').removeClass('text-warning');
    } else if (length > 700) {
        $(counterId).addClass('text-warning').removeClass('text-danger');
    } else {
        $(counterId).removeClass('text-warning text-danger');
    }
}

// ===== FEE CALCULATION =====
function calculateDue(total, paid) {
    return total - paid;
}

function updateFeeDetails() {
    var studentId = $('#student_select').val();
    var feeHeadId = $('#fee_head').val();
    
    if (studentId && feeHeadId) {
        $.ajax({
            url: API_URL + 'get-fee-details.php',
            type: 'POST',
            data: {
                student_id: studentId,
                fee_head_id: feeHeadId
            },
            dataType: 'json',
            success: function(data) {
                $('#fee_details').show();
                $('#total_amount').text('৳' + data.amount.toFixed(2));
                $('#paid_amount').text('৳' + data.paid.toFixed(2));
                $('#due_amount_display').text('৳' + data.due.toFixed(2));
                $('#due_date_display').text(data.due_date || 'Not set');
                $('#paying_amount').attr('max', data.due);
            }
        });
    }
}
// Dynamic Counter Animation
function startCounters() {
    const counters = document.querySelectorAll('.counter');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000; // 2 seconds
        const step = target / (duration / 16); // 60fps
        let current = 0;
        
        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.innerText = Math.ceil(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.innerText = target;
            }
        };
        
        // Start animation when element is in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        observer.observe(counter);
    });
}

// Initialize on page load and after AOS
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure AOS is ready
    setTimeout(startCounters, 500);
});

// ===== BULK ACTIONS =====
function selectAll(checkboxClass) {
    $(checkboxClass).prop('checked', true);
}

function deselectAll(checkboxClass) {
    $(checkboxClass).prop('checked', false);
}

// ===== FILE UPLOAD PREVIEW =====
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            $('#' + previewId).attr('src', e.target.result).show();
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// ===== PRINT RECEIPT =====
function printReceipt(collectionId) {
    $.ajax({
        url: API_URL + 'get-receipt.php',
        type: 'POST',
        data: { id: collectionId },
        success: function(data) {
            var printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Fee Receipt</title>
                        <link rel="stylesheet" href="../assets/css/style.css">
                        <style>
                            body { padding: 20px; }
                            @media print {
                                body { padding: 0; }
                            }
                        </style>
                    </head>
                    <body>${data}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
    });
}

// ===== SEND SMS =====
function sendSMS(phone, message, callback) {
    $.ajax({
        url: API_URL + 'send-sms.php',
        type: 'POST',
        data: {
            phone: phone,
            message: message
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('SMS sent successfully', 'success');
            } else {
                showNotification('Failed to send SMS', 'error');
            }
            if (callback) callback(response);
        }
    });
}

// ===== EXPORT DATA =====
function exportData(type, params) {
    var url = API_URL + 'export-' + type + '.php?' + $.param(params);
    window.location.href = url;
}

// ===== INITIALIZE ON PAGE LOAD =====
$(document).ready(function() {
    initDashboardCharts();
    initCounters();
    initStudentSearch();
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Confirm delete actions
    $('.delete-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
});