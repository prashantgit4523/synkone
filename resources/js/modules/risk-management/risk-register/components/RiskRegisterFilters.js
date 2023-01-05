import React from 'react';

const RiskRegisterFilters = (props) => {
    const {
        searchTerm,
        onTermChange,
        onlyIncomplete,
        onCheck,
        title=""
    } = props;
    return (
        <div className="middle-box pb-2 d-flex justify-content-between">
            <div className="searchbox top__search">
                <h4 className="me-5 p-8 mr-11">{title}</h4>
            </div>
            
            <div className="text__box d-flex display-info">
                <h5 className="pt-md-1 display-info__allign me-1">Display only risks with incomplete information</h5>
                <div className="checkbox checkbox-success mid__checkbox ">
                    <input id="updated-risks-filter" checked={onlyIncomplete} onChange={onCheck} type="checkbox" />
                    <label htmlFor="updated-risks-filter" />
                </div>
            </div>
        </div>
    )
};

export default RiskRegisterFilters;
