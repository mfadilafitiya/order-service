from flask import jsonify, request

from app.exceptions import (
    DuplicateReviewError,
    ProductNotFoundError,
    ProductServiceUnavailableError,
    ReviewNotFoundError,
    ValidationError,
)
from app.services import review_service

def get_reviews(product_id):
    try:
        data = review_service.get_reviews_for_product(product_id)
        return jsonify(data), 200
    except ProductNotFoundError as err:
        return jsonify({"error": str(err)}), 404
    except ProductServiceUnavailableError as err:
        return jsonify({"error": str(err), "details": str(err.cause) if err.cause else None}), 503


def get_review_by_id(review_id):
    try:
        data = review_service.get_review_by_id(review_id)
        return jsonify(data), 200
    except ReviewNotFoundError as err:
        return jsonify({"error": str(err)}), 404


def create_review():
    try:
        payload = request.get_json(silent=True) or {}
        created = review_service.create_review(payload)
        return jsonify(created), 201
    except ValidationError as err:
        return jsonify({"errors": err.errors}), 400
    except ProductNotFoundError as err:
        return jsonify({"error": str(err)}), 404
    except DuplicateReviewError as err:
        return jsonify({"error": str(err)}), 409
    except ProductServiceUnavailableError as err:
        return jsonify({"error": str(err), "details": str(err.cause) if err.cause else None}), 503

def update_review(review_id):
    try:
        payload = request.get_json(silent=True) or {}
        updated = review_service.update_review(review_id, payload)
        return jsonify(updated), 200
    except ValidationError as err:
        return jsonify({"errors": err.errors}), 400
    except ReviewNotFoundError as err:
        return jsonify({"error": str(err)}), 404

def delete_review(review_id):
    try:
        review_service.delete_review(review_id)
        return jsonify({"message": "Review berhasil dihapus"}), 200
    except ReviewNotFoundError as err:
        return jsonify({"error": str(err)}), 404