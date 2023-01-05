import React, { Fragment, useEffect, useState } from "react";
import { defaultMemoize } from "reselect";

function RiskStandardSelection(props) {
    const [standards, setprojectData] = useState([]);
    const [inputName, setInputName] = useState([]);

    useEffect(async () => {
        if (props.riskStandards) {
            setprojectData(props.riskStandards);
        }
        if (props.inputName) {
            setInputName(props.inputName);
        }
    }, [props]);

    const selectItem = (e) => {
        props.selectStandard
            ? props.selectStandard(e)
            : props.selectApproach(e);
            props.setSelectedApproach(null);
            props.setReachedWizardTab(1);
    };

    return (
        <Fragment>
            {standards
                ? standards.map(function (datum, index) {
                      return (
                          <div key={index} className="col-xl-4 col-lg-4 col-md-6 standard-box riskStandardSelectionBox">
                            <div className="card bg-pattern h-100 riskStandardInnerDiv">
                                <div className="card-body">
                                    <div className="clearfix" />
                                    <div className="text-center">
                                        <img src={datum.logo} alt="" className="avatar-xl mb-3" />
                                        <h4 className="mb-1 font-20 clamp clamp-1">{decodeHTMLEntity(datum.name)}</h4>
                                    </div>

                                    <p className="description font-14 text-center text-muted">{datum.description}</p>

                                    <div className="text-center">
                                    <div className="checkbox-btn">
                                              <input
                                                  type="radio"
                                                  name={inputName}
                                                  onClick={selectItem}
                                                  defaultValue={datum.name}
                                                  aria-label={datum.name}
                                                  id={datum.name}
                                              />
                                              <label htmlFor={datum.name}>
                                                  Choose
                                              </label>
                                          </div>
                                    </div>
                                </div>
                                </div>
                              {/* <div className="card">
                                  <div className="card-body project-box br-dark">
                                      <div className="head-text text-center">
                                          <h4>{datum.standardName}</h4>
                                          <p className="my-3 iso-subtext">
                                              {datum.description}
                                          </p>
                                          <div className="checkbox-btn">
                                              <input
                                                  type="radio"
                                                  name={inputName}
                                                  onClick={selectItem}
                                                  defaultValue={datum.value}
                                                  aria-label={datum.value}
                                                  id={datum.value}
                                              />
                                              <label htmlFor={datum.value}>
                                                  Choose
                                              </label>
                                          </div>
                                      </div>
                                  </div>
                              </div> */}
                          </div>
                      );
                  })
                : ""}
        </Fragment>
    );
}

export default RiskStandardSelection;
