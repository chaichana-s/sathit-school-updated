$(document).ready(function () {
    // ---- Sidebar Toggle Logic ----
    // On mobile, we might need an overlay. I will add it dynamically if not present.
    if ($(window).width() <= 768) {
        if ($('.overlay').length === 0) {
            $('body').append('<div class="overlay"></div>');
        }
    }

    // Toggle Sidebar
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
        if ($(window).width() <= 768) {
            $('.overlay').toggleClass('active');
        }
    });

    // Close sidebar when clicking on overlay (mobile)
    $('body').on('click', '.overlay', function () {
        $('#sidebar').removeClass('active');
        $(this).removeClass('active');
    });
    
    // Re-check overlay when resizing
    $(window).resize(function() {
        if ($(window).width() > 768) {
            $('.overlay').removeClass('active');
            $('#sidebar').removeClass('active'); // reset to default visible on desktop
        }
    });

    // ---- Dashboard Live Polling & Chart.js Initialization ----
    
    var attendanceChart = null;
    var gradeChart = null;

    function initOrUpdateCharts(data) {
        // Attendance Chart
        var attCtx = document.getElementById('attendanceChart');
        if (attCtx && data.charts.attendance) {
            var labels = data.charts.attendance.labels;
            var attData = data.charts.attendance.data;
            
            if (attendanceChart) {
                attendanceChart.data.labels = labels;
                attendanceChart.data.datasets[0].data = attData;
                attendanceChart.update();
            } else {
                var gradient = attCtx.getContext('2d').createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(79, 70, 229, 0.4)');
                gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

                attendanceChart = new Chart(attCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: ' อัตราการมาเรียนเฉลี่ย (%)',
                            data: attData,
                            backgroundColor: gradient,
                            borderColor: '#4f46e5',
                            borderWidth: 3,
                            pointBackgroundColor: '#0284c7',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#0284c7',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0f172a',
                                titleFont: { family: "'Outfit', sans-serif", size: 14, weight: 600 },
                                bodyFont: { family: "'Inter', sans-serif", size: 13 },
                                padding: 12,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            y: {
                                min: 0,
                                max: 100,
                                grid: { color: '#e2e8f0', borderDash: [5, 5] },
                                ticks: { font: { family: "'Inter', sans-serif" } }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: "'Inter', sans-serif" } }
                            }
                        }
                    }
                });
            }
        }

        // Grade Distribution Chart
        var gradeCtx = document.getElementById('gradeChart');
        if (gradeCtx && data.charts.grades) {
            if (gradeChart) {
                gradeChart.data.labels = data.charts.grades.labels;
                gradeChart.data.datasets[0].data = data.charts.grades.data;
                gradeChart.update();
            } else {
                gradeChart = new Chart(gradeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.charts.grades.labels,
                        datasets: [{
                            data: data.charts.grades.data,
                            backgroundColor: ['#4f46e5', '#0284c7', '#38bdf8', '#94a3b8', '#e2e8f0'],
                            borderWidth: 0,
                            borderColor: '#fff',
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { font: { family: "'Inter', sans-serif" }, usePointStyle: true, padding: 20 }
                            },
                            tooltip: {
                                backgroundColor: '#0f172a',
                                bodyFont: { family: "'Inter', sans-serif" },
                                padding: 10,
                                cornerRadius: 8
                            }
                        }
                    }
                });
            }
        }
    }

    function fetchDashboardData() {
        $.ajax({
            url: APP_BASE + '/api/dashboard_api.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update stats
                    $('#dashboard-teachers').text(response.stats.teachers);
                    $('#dashboard-students').text(response.stats.students);
                    $('#dashboard-subjects').text(response.stats.subjects);
                    $('#dashboard-attendance').text(response.stats.attendance_rate);
                    
                    // Update Today's Summary Text dynamically (if the elements exist)
                    if ($('#dashboard-today-summary-text').length && response.today_summary) {
                        var tSm = response.today_summary;
                        var d = new Date();
                        var timeStr = d.toLocaleDateString('en-GB') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                        
                        var newHtml = 'ระบบฐานข้อมูลสถิติของโรงเรียนสาธิตวิทยา — ' + timeStr + ' น.';
                        if (tSm.total > 0) {
                            newHtml += ' | วันนี้บันทึกเวลาเรียนแล้ว <strong>' + tSm.total.toLocaleString() + '</strong> รายการ ' +
                                       '(มา <span>' + tSm.present.toLocaleString() + '</span> | ' +
                                       'ขาด <span>' + tSm.absent.toLocaleString() + '</span> | ' +
                                       'สาย <span>' + tSm.late.toLocaleString() + '</span>)';
                        } else {
                            newHtml += ' | <span>ยังไม่มีการบันทึกเวลาเรียนสำหรับวันนี้</span>';
                        }
                        $('#dashboard-today-summary-text').html(newHtml);
                    }

                    // Update Recent Activities
                    if ($('#dashboard-recent-activities').length && response.recent_activities) {
                        var actsHtml = '';
                        response.recent_activities.forEach(function(act) {
                            // Ensure text escapes HTML
                            var sanitizeDiv = document.createElement('div');
                            sanitizeDiv.textContent = act.text;
                            var safeText = sanitizeDiv.innerHTML;
                            var sanitizeRec = document.createElement('div');
                            sanitizeRec.textContent = act.recorder;
                            var safeRec = sanitizeRec.innerHTML;

                            actsHtml += '<li class="list-group-item px-4 py-3 d-flex align-items-center">' +
                                '<div class="activity-icon bg-' + act.color + ' bg-opacity-10 text-' + act.color + ' rounded-circle p-2 me-3">' +
                                    '<i class="fas fa-' + act.icon + '"></i>' +
                                '</div>' +
                                '<div>' +
                                    '<p class="mb-0 fw-medium">' + safeText + '</p>' +
                                    '<small class="text-muted"><i class="fas fa-clock me-1"></i>' + act.time + ' — โดย ' + safeRec + '</small>' +
                                '</div>' +
                            '</li>';
                        });
                        $('#dashboard-recent-activities').html(actsHtml);
                    }

                    // Update charts
                    initOrUpdateCharts(response);
                } else {
                    console.error('Failed to load dashboard data:', response.error);
                }
            },
            error: function(err) {
                console.error('AJAX Error:', err);
            }
        });
    }

    if ($('#attendanceChart').length > 0) {
        fetchDashboardData();
        // Set polling interval for 5 seconds (5000 ms) for real-time syncing
        setInterval(fetchDashboardData, 5000);
    }
});
