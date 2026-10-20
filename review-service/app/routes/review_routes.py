from flask import Blueprint
from app.controllers import review_controller

review_bp = Blueprint("reviews", __name__)

@review_bp.route("/products/<int:product_id>/reviews", methods=["GET"])
def get_reviews(product_id):
    return review_controller.get_reviews(product_id)

@review_bp.route("/reviews", methods=["POST"])
def create_review():
    return review_controller.create_review()

@review_bp.route("/reviews/<string:review_id>", methods=["GET", "PUT", "DELETE"])
def handle_review_by_id(review_id):
    from flask import request
    if request.method == "GET":
        return review_controller.get_review_by_id(review_id)
    elif request.method == "PUT":
        return review_controller.update_review(review_id)
    elif request.method == "DELETE":
        return review_controller.delete_review(review_id)

@review_bp.route("/reviews/<string:review_id>", methods=["PUT"])
def update_review(review_id):
    return review_controller.update_review(review_id)

@review_bp.route("/reviews/<string:review_id>", methods=["DELETE"])
def delete_review(review_id):
    return review_controller.delete_review(review_id)