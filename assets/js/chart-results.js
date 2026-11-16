/**
 * ApexVote Pro - Chart.js Visualization Engine
 * Localized for Kolkata, West Bengal, India
 * Palette: Sophisticated Dark Yellow, Mustard & Warm Earth Design System
 */

window.ApexVoteCharts = {
    // Dark Yellow & Brown Design System Palette
    palette: [
        '#F4CB45', // Primary Mustard Yellow
        '#DDB63F', // Secondary Mustard Gold
        '#E8B84A', // Marigold / Honey
        '#38B982', // Warm Emerald
        '#D97746', // Warm Terracotta
        '#C28E46', // Ochre
        '#E16B62', // Warm Coral
        '#C7B69F'  // Sand Beige
    ],

    /**
     * Render Interactive Horizontal or Vertical Bar Chart
     */
    createBarChart: function(canvasId, labels, votes, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        const colors = this.palette.slice(0, labels.length);
        const totalVotes = votes.reduce((a, b) => a + b, 0);

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Certified Votes',
                    data: votes,
                    backgroundColor: colors.map(c => c + 'DD'),
                    borderColor: colors,
                    borderWidth: 1.5,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#514032',
                        titleColor: '#F6EEDC',
                        bodyColor: '#D1C2AC',
                        borderColor: '#5A4738',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const pct = totalVotes > 0 ? ((val / totalVotes) * 100).toFixed(1) : 0;
                                return ` Votes Recorded: ${val.toLocaleString()} (${pct}%)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(246, 238, 220, 0.06)'
                        },
                        ticks: {
                            color: '#A9977F',
                            font: { family: "'Plus Jakarta Sans', 'Inter', sans-serif", size: 12 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(246, 238, 220, 0.06)'
                        },
                        ticks: {
                            color: '#A9977F',
                            precision: 0,
                            font: { family: "'Plus Jakarta Sans', 'Inter', sans-serif", size: 12 }
                        }
                    }
                }
            }
        });
    },

    /**
     * Render Doughnut Distribution Chart
     */
    createDoughnutChart: function(canvasId, labels, votes) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        const colors = this.palette.slice(0, labels.length);
        const totalVotes = votes.reduce((a, b) => a + b, 0);

        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: votes,
                    backgroundColor: colors,
                    borderColor: '#241B16',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#F6EEDC',
                            padding: 16,
                            font: { family: "'Plus Jakarta Sans', 'Inter', sans-serif", size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#514032',
                        titleColor: '#F6EEDC',
                        bodyColor: '#D1C2AC',
                        borderColor: '#5A4738',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const pct = totalVotes > 0 ? ((val / totalVotes) * 100).toFixed(1) : 0;
                                return ` ${context.label}: ${val} votes (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
};
