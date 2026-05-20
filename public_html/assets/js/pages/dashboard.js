/**
 * BugTracker - Dashboard Charts & Logic
 * Yêu cầu: Đã load thư viện Chart.js qua CDN
 */
const Dashboard = {
    init() {
        this.initStatusChart();
        this.initTrendsChart();
    },

    // Lấy bảng màu hệ thống để đồng bộ với CSS (Design Tokens)
    getColors() {
        return {
            primary: '#2E86AB',
            success: '#27ae60',
            danger: '#e74c3c',
            warning: '#f39c12',
            info: '#17a2b8',
            gray: '#e1e4e8',
            text: '#7f8c8d'
        };
    },

    initStatusChart() {
        const canvas = document.getElementById('statusDistributionChart');
        if (!canvas) return;

        try {
            // Đọc dữ liệu JSON an toàn từ thuộc tính data
            const chartData = JSON.parse(canvas.getAttribute('data-chart'));
            const colors = this.getColors();

            // Ánh xạ màu sắc tương ứng với trạng thái (Open, In Progress, Resolved, Closed)
            const backgroundColors = chartData.labels.map(label => {
                const lowerLabel = label.toLowerCase();
                if (lowerLabel.includes('open')) return colors.danger;
                if (lowerLabel.includes('progress')) return colors.warning;
                if (lowerLabel.includes('resolved')) return colors.info;
                if (lowerLabel.includes('closed')) return colors.success;
                return colors.gray;
            });

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.values,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%', // Tạo độ rỗng cho donut chart
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { family: "'Inter', sans-serif", size: 12 }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Lỗi khi vẽ biểu đồ Trạng thái:', error);
            canvas.parentElement.innerHTML = '<div class="text-center text-muted p-4">Không thể tải dữ liệu biểu đồ.</div>';
        }
    },

    initTrendsChart() {
        const canvas = document.getElementById('weeklyTrendsChart');
        if (!canvas) return;

        try {
            const chartData = JSON.parse(canvas.getAttribute('data-chart'));
            const colors = this.getColors();

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: chartData.labels, // Các mốc thời gian (Tuần)
                    datasets: [{
                        label: 'Số lượng Issue tạo mới',
                        data: chartData.values,
                        borderColor: colors.primary,
                        backgroundColor: 'rgba(46, 134, 171, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: colors.primary,
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: colors.primary,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.3 // Làm cong mượt đường line
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1, // Hiển thị số nguyên
                                font: { family: "'Inter', sans-serif" }
                            },
                            grid: {
                                borderDash: [4, 4],
                                color: colors.gray
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: "'Inter', sans-serif" } }
                        }
                    },
                    plugins: {
                        legend: { display: false }, // Ẩn legend vì chỉ có 1 đường
                        tooltip: {
                            backgroundColor: '#343a40',
                            padding: 12,
                            titleFont: { family: "'Inter', sans-serif", size: 13 },
                            bodyFont: { family: "'Inter', sans-serif", size: 13 },
                            displayColors: false
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Lỗi khi vẽ biểu đồ Xu hướng:', error);
            canvas.parentElement.innerHTML = '<div class="text-center text-muted p-4">Không thể tải dữ liệu biểu đồ.</div>';
        }
    }
};

// Khởi chạy khi DOM đã sẵn sàng
document.addEventListener('DOMContentLoaded', () => Dashboard.init());