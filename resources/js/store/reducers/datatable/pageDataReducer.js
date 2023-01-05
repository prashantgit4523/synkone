const initialState = {
    perPage: 10,
    currentPage: 1
}

const pageDataReducer = (state = initialState, action) => {
    switch (action.type) {
        case 'store_per_page_data':
            return {
                ...state,
                perPage: action.payload
            }
        case 'store_current_page_data':
            return {
                ...state,
                currentPage: action.payload
            }
        default: return state
    }
}

export default pageDataReducer 