/**
 * CoachingPro - Main JavaScript
 * Author: Your Name
 * Version: 1.0
 */

// ===== GLOBAL VARIABLES =====
const API_URL = window.location.origin + '/coaching-mgmt/admin/';
let currentUser = null;

// ===== DOCUMENT READY =====
$(document).ready(function() {
    console.log('CoachingPro initialized');
    
    // Initialize all components
    initTooltips();
    initPopovers();
    initDatePickers();
    initSelect2();
    initDataTables();
    initFormValidation();
    initAutoDismissAlerts();
    initSmoothScroll();
    
    // Load user data if logged in
    if (typeof USER_ID !== 'undefined') {
        loadUserData();
    }
});

// ===== INITIALIZATION FUNCTIONS =====
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function initPopovers() {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

function initDatePickers() {
    if ($('.datepicker').length) {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }
}

function initSelect2() {
    if ($('.select2').length) {
        $('.select2').select2({
            placeholder: 'Select option',
            allowClear: true
        });
    }
}

function initDataTables() {
    if ($('.datatable').length) {
        $('.datatable').DataTable({
            pageLength: 10,
            ordering: true,
            info: true,
            searching: true,
            lengthChange: false,
            language: {
                emptyTable: 'No data available',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'Showing 0 to 0 of 0 entries',
                search: 'Search:',
                paginate: {
                    first: 'First',
                    last: 'Last',
                    next: 'Next',
                    previous: 'Previous'
                }
            }
        });
    }
}

function initFormValidation() {
    $('form[data-validate="true"]').each(function() {
        $(this).on('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showNotification('Please fill all required fields', 'error');
            }
        });
    });
}

function initAutoDismissAlerts() {
    $('.alert').each(function() {
        var alert = $(this);
        setTimeout(function() {
            alert.fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    });
}

function initSmoothScroll() {
    $('a[href*="#"]').on('click', function(e) {
        if (this.hash !== '') {
            e.preventDefault();
            var hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 70
            }, 800);
        }
    });
}

// ===== NOTIFICATION SYSTEM =====
function showNotification(message, type = 'success') {
    var icon = '';
    var bgClass = '';
    
    switch(type) {
        case 'success':
            icon = 'fa-check-circle';
            bgClass = 'alert-success';
            break;
        case 'error':
            icon = 'fa-exclamation-circle';
            bgClass = 'alert-danger';
            break;
        case 'warning':
            icon = 'fa-exclamation-triangle';
            bgClass = 'alert-warning';
            break;
        case 'info':
            icon = 'fa-info-circle';
            bgClass = 'alert-info';
            break;
    }
    
    var notification = `
        <div class="alert ${bgClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(notification);
    
    setTimeout(function() {
        $('.alert').last().fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}

// ===== FORM VALIDATION =====
function validateForm(form) {
    var isValid = true;
    var requiredFields = $(form).find('[required]');
    
    requiredFields.each(function() {
        if (!$(this).val()) {
            $(this).addClass('error');
            isValid = false;
        } else {
            $(this).removeClass('error');
        }
    });
    
    return isValid;
}

// ===== AJAX LOADING =====
function showLoading(container) {
    var loader = `
        <div class="text-center py-5">
            <div class="spinner mb-3"></div>
            <p class="text-muted">Loading...</p>
        </div>
    `;
    $(container).html(loader);
}

function hideLoading(container) {
    $(container).find('.spinner').remove();
}

// ===== NUMBER FORMATTING =====
function formatNumber(num, decimals = 2) {
    return parseFloat(num).toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatCurrency(amount) {
    return '৳' + formatNumber(amount);
}

// ===== DATE FORMATTING =====
function formatDate(dateString, format = 'dd-mm-yyyy') {
    var date = new Date(dateString);
    var day = date.getDate().toString().padStart(2, '0');
    var month = (date.getMonth() + 1).toString().padStart(2, '0');
    var year = date.getFullYear();
    
    switch(format) {
        case 'dd-mm-yyyy':
            return day + '-' + month + '-' + year;
        case 'yyyy-mm-dd':
            return year + '-' + month + '-' + day;
        case 'dd MMM yyyy':
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return day + ' ' + months[date.getMonth()] + ' ' + year;
        default:
            return day + '-' + month + '-' + year;
    }
}

// ===== LOCAL STORAGE =====
function saveToStorage(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
}

function getFromStorage(key) {
    var value = localStorage.getItem(key);
    return value ? JSON.parse(value) : null;
}

function removeFromStorage(key) {
    localStorage.removeItem(key);
}

// ===== COOKIE HANDLING =====
function setCookie(name, value, days = 7) {
    var expires = '';
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + (value || '') + expires + '; path=/';
}

function getCookie(name) {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

// ===== PRINT FUNCTIONS =====
function printElement(elementId) {
    var printContent = document.getElementById(elementId).innerHTML;
    var originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// ===== EXPORT TO EXCEL =====
function exportToExcel(tableId, filename = 'export') {
    var table = document.getElementById(tableId);
    var html = table.outerHTML;
    var url = 'data:application/vnd.ms-excel,' + escape(html);
    var link = document.createElement('a');
    link.download = filename + '.xls';
    link.href = url;
    link.click();
}

// ===== CONFIRMATION DIALOG =====
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// ===== SCROLL TO TOP =====
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// ===== LOAD USER DATA =====
function loadUserData() {
    $.ajax({
        url: API_URL + 'get-user-data.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            currentUser = data;
        }
    });
}

// ===== ERROR HANDLING =====
window.onerror = function(msg, url, lineNo, columnNo, error) {
    console.error('Error: ' + msg + '\nURL: ' + url + '\nLine: ' + lineNo);
    return false;
};

// ===== DEBOUNCE FUNCTION =====
function debounce(func, wait, immediate) {
    var timeout;
    return function() {
        var context = this, args = arguments;
        var later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}