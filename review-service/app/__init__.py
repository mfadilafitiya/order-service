import os
from flask import Flask
from app.database import init_db
from app.routes.review_routes import review_bp

def create_app():
    app = Flask(__name__)
    
    # Inisialisasi koneksi database MongoDB
    init_db()

    # Daftarkan Blueprint API
    app.register_blueprint(review_bp)

    @app.route("/", methods=["GET"])
    def index():
        return {"message": "Review Service is running", "status": "ok"}

    
    @app.route("/health", methods=["GET"])
    def health_check():
        return {"status": "healthy"}, 200
  

    return app