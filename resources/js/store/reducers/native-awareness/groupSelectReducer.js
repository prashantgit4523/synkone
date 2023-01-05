const initialState = {
    selectedGroup: "All SSO users",
}

const groupSelectReducer = (state = initialState, action) => {
    switch (action.type) {
        case 'group_select':
            return {
                ...state,
                selectedGroup: action.payload
            }
        default: return state
    }
}

export default groupSelectReducer