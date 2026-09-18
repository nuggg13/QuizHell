// Shared response parsing; callers retain their own transport and timeout policy.
export async function readJsonResponse(response, fallbackMessage) {
    const result = await response.json();
    if (!response.ok) {
        const error = new Error(result.error || fallbackMessage);
        error.status = response.status;
        throw error;
    }
    return result;
}
