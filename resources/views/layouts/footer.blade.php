<!-- Footer Section -->
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container-custom">
        <div class="row">
            <div class="col-md-4">
                <h5>About Us</h5>
                <p>We aim to contribute to preserving the Kirundi language.</p>
            </div>
            <div class="col-md-4">
                <h5>Links</h5>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-white">About</a></li>
                    <li><a href="#" class="text-white">Terms & Conditions</a></li>
                    <li><a href="#" class="text-white">Privacy Policy</a></li>
                    <li><a href="#" class="text-white">Contact Us</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Follow Us</h5>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-white">Facebook</a></li>
                    <li><a href="#" class="text-white">Twitter</a></li>
                    <li><a href="#" class="text-white">Instagram</a></li>
                    <li><a href="#" class="text-white">LinkedIn</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="text-center py-2">
        <small>&copy; {{ date('Y') }} Makira. All rights reserved.</small>
    </div>
</footer>

<!-- JS scripts -->
<!-- Scripts -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- Include jQuery locally -->
<script src="{{ asset('js/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('js/autocomplete.js') }}"></script>

<!-- End Footer Section -->
