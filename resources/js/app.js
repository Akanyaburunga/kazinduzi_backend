import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

import axios from 'axios';

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    const resultsList = document.getElementById('autocomplete-results');

    searchInput.addEventListener('input', async () => {
        const query = searchInput.value;

        if (query.length > 2) { // Trigger search only after 3+ characters
            try {
                const response = await axios.get('/autocomplete', {
                    params: { query },
                });

                // Clear previous results
                resultsList.innerHTML = '';

                // Populate the results
                response.data.forEach(word => {
                    const li = document.createElement('li');
                    li.textContent = word;
                    li.classList.add('list-group-item', 'list-group-item-action');
                    li.addEventListener('click', () => {
                        searchInput.value = word; // Fill input with the selected word
                        resultsList.innerHTML = ''; // Clear suggestions
                    });
                    resultsList.appendChild(li);
                });
            } catch (error) {
                console.error('Error fetching autocomplete suggestions:', error);
            }
        } else {
            resultsList.innerHTML = ''; // Clear suggestions for short input
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!resultsList.contains(e.target) && e.target !== searchInput) {
            resultsList.innerHTML = '';
        }
    });
});
