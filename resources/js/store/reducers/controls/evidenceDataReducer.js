const initialState = {
    evidenceData : []
}

const evidenceDataReducer = (state = initialState,action) => {
    if(action.type == 'store_evidence_data'){
        return {
            ...state,
            evidenceData: action.payload
        }
    }else{
        return state
    }
}

export default evidenceDataReducer