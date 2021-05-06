import axios from 'axios';

export default class Notification {
    /**
     * Fetch a paginated user list.
     *
     * @param {object} params
     *
     * @return {object}
     */
    static async paginated(params = {}) {
        const response = await axios.get('/api/v1/notifications', {
            params,
        });

        if (response.status !== 200) {
            return {};
        }

        return response.data;
    }


    /**
     * Update a user.
     *
     * @param {number} id
     * @param {object} attributes
     *
     * @return {object}
     */
    static async update(id, attributes) {
        const response = await axios.patch(`/api/v1/notifications/${id}`, attributes);

        if (response.status !== 200) {
            return {};
        }

        return response.data;
    }

    /**
     * Delete a user.
     *
     * @param {number} id
     *
     * @return {object}
     */
    static async delete(id) {
        const response = await axios.delete(`/api/v1/notifications/${id}`);

        if (response.status !== 200) {
            return {};
        }

        return response.data;
    }

}
