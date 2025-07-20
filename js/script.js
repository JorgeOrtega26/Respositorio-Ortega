const donutOptions = {
  cutout: '70%',
  plugins: { legend: { display: false } }
};

new Chart(document.getElementById('applicantsChart'), {
  type: 'doughnut',
  data: { datasets: [{ data: [3154, 5231-3154], backgroundColor: ['#1abc9c', '#ecf0f1'] }] },
  options: donutOptions
});

new Chart(document.getElementById('interviewsChart'), {
  type: 'doughnut',
  data: { datasets: [{ data: [1546, 5231-1546], backgroundColor: ['#3498db', '#ecf0f1'] }] },
  options: donutOptions
});

new Chart(document.getElementById('forwardsChart'), {
  type: 'doughnut',
  data: { datasets: [{ data: [912, 5231-912], backgroundColor: ['#9b59b6', '#ecf0f1'] }] },
  options: donutOptions
});

new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: ['S','M','T','W','T','F','S','S','M','T','W','T','F','S'],
    datasets: [{ label: 'Applicants', data: [50,200,300,382,250,180,100,30,300,350,280,200,150,80] }]
  },
  options: { responsive: true, plugins: { tooltip: { callbacks: { title: () => '', label: ctx => `${ctx.parsed.y} Applicants` } } }, scales: { y: { beginAtZero: true } } }
});
