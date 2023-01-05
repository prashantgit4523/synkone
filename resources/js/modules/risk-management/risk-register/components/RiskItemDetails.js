import React, { useEffect, useState } from "react";

import Slider from "rc-slider";
import Select from "../../../../common/custom-react-select/CustomReactSelect";

import { useForm, usePage } from "@inertiajs/inertia-react";
import LoadingButton from "../../../../common/loading-button/LoadingButton";

import 'rc-slider/assets/index.css';
import { showToastMessage } from "../../../../utils/toast-message";

import 'react-autocomplete-input/dist/bundle.css';
import '../styles/autocomplete.css';
import { useForm as useReactForm , Controller} from 'react-hook-form';
import CustomCreatableSelect from "../../../../common/custom-creatable-select/CustomCreatableSelect";

const RiskItemDetails = ({
    risk,
    removeRiskFromTable,
    primaryFilters,
    onUpdateCategoryRisksCount,
    handleUpdateRiskStatus,
    updateRiskTableRow,
    clickable
    // risksAffectedProperties,
    // riskMatrixLikelihoods,
    // riskMatrixImpacts
}) => {
    const [status, setStatus] = useState(risk.status);
    const [selected, setSelected] = useState(risk.affected_functions_or_assets);
    const [inherentScore, setInherentScore] = useState(risk.inherent_score);
    const [residualScore, setResidualScore] = useState(risk.residual_score);
    const [inherentRiskScoreLevel, setInherentRiskScoreLevel] = useState(
        risk.InherentRiskScoreLevel
    );
    const [residualRiskScoreLevel, setResidualRiskScoreLevel] = useState(
        risk.ResidualRiskScoreLevel
    );
    
    const { control, handleSubmit, formState: { errors: error1 } , reset } = useReactForm({
        defaultValues: {}
    });
    
    const {
        risksAffectedProperties,
        riskMatrixImpacts,
        riskMatrixLikelihoods,
        riskMatrixScores,
        riskScoreActiveLevelType,
        request_url,
        assets
    } = usePage().props;

    //Merging selected values and assets from the assets table(db) and removing duplicates
    const affectedFunctionsSelectOptions = assets.concat(selected).filter((arr, index, self) =>
    index === self.findIndex((t) => (t.label === arr.label && t.value === arr.value)))

    const { processing, data, errors, setData, post } = useForm({
        treatment_options: risk.treatment_options,
        affected_functions_or_assets: risk.affected_functions_or_assets,
        affected_properties: risk.affected_properties,
        impact: risk.impact - 1,
        likelihood: risk.likelihood - 1,
    });

    useEffect(() => {
        reset({
            affected_properties: risk.affected_properties,
            affected_functions_or_assets: risk.affected_functions_or_assets,
        });
    }, [risk]);

    const onSubmit = () => post(route('risks.register.risks-update-react', risk.id), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            // display toast message
            showToastMessage('Risk updated successfully!', 'success');
            // if we only filtered by incomplete, after save the risk should be removed
            // since we only need the incomplete ones
            if (primaryFilters.only_incomplete) return removeRiskFromTable(risk.id);
            // we're not filtering by incomplete, so now if it's incomplete, we should offset the
            // incomplete *only* by 1
            if (!risk.is_complete && request_url.includes('dashboard')) {
                // onUpdateCategoryRisksCount(risk.category_id, 0, 1);
                handleUpdateRiskStatus(risk.id, 1);
            }
            updateRiskTableRow(data, risk.id, inherentScore, residualScore);
            setStatus(data.treatment_options === 'Accept' ? 'Close' : 'Open');
        }
    });
    useEffect(() => {
        setData('affected_functions_or_assets',selected);
    },[selected]);

    const handleAffectedPropertiesSelect = (values) =>
        setData(
            "affected_properties",
            [...values.map((v) => v.value)].join(",")
        );

    const handleSliderChange = (type) => (value) => {
        setData(type, value);
        const scores = riskMatrixScores.flat();
        const maxScore = Math.max.apply(
            Math,
            scores.map((s) => s.score)
        );
        const { score: riskScore } = scores.find(
            (s) =>
                s.likelihood_index ===
                (type === "likelihood" ? value : data.likelihood) &&
                s.impact_index === (type === "impact" ? value : data.impact)
        );
        const scoreLevel = riskScoreActiveLevelType.levels.find(
            (level, key) => {
                let index = parseInt(key);
                let lastIndex = riskScoreActiveLevelType.levels.length - 1;
                let startScore =
                    index === 0
                        ? 1
                        : riskScoreActiveLevelType.levels[index - 1][
                        "max_score"
                        ] + 1;
                let endScore =
                    index === lastIndex ? maxScore : level["max_score"];

                /* Giving matrix cell color if it falls within the range */
                return riskScore >= startScore && riskScore <= endScore;
            }
        );

        setInherentRiskScoreLevel({
            ...inherentRiskScoreLevel,
            color: scoreLevel.color,
            name: scoreLevel.name,
        });
        setResidualRiskScoreLevel({
            ...residualRiskScoreLevel,
            color: scoreLevel.color,
            name: scoreLevel.name,
        });
        setInherentScore(riskScore);
        setResidualScore(riskScore);
    };

    const risksAffectedPropertiesSelectOptions = [
        {
            label: "Common",
            options: risksAffectedProperties.common.map((c) => ({
                label: c,
                value: c,
            })),
        },
        {
            label: "Other",
            options: risksAffectedProperties.other.map((o) => ({
                label: o,
                value: o,
            })),
        },
    ];

    return (

        <div className="risk-item-expand p-3 m-2 border">
        <form onSubmit={handleSubmit(onSubmit)}>
            <div className="row">
                <div className="col-xl-12 col-lg-12 col-md-12 col-sm-11 col-10">
                    <div className="expanded__box-description">
                        <h4 className="">Description:</h4>
                        <p className="m-0 p-0 text-break">{risk.risk_description}</p>
                    </div>
                </div>

                <div className="col-xl-8 col-lg-8 col-md-8 col-sm-6 col-10">
                    <div className="slider-div py-3">
                        <div className="slider__1">
                            <span>
                                <h5 className="m-0 p-0">Likelihood:</h5>
                            </span>
                            <Slider
                                defaultValue={data.likelihood}
                                marks={riskMatrixLikelihoods}
                                max={riskMatrixLikelihoods.length - 1}
                                onChange={handleSliderChange("likelihood")}
                                tooltipVisible={false}
                            />
                        </div>

                        <div
                            className="slider__2 pt-2"
                            style={{ marginTop: "15px" }}
                        >
                            <h5 className="m-0">Impact:</h5>
                            <Slider
                                defaultValue={data.impact}
                                marks={riskMatrixImpacts}
                                max={riskMatrixImpacts.length - 1}
                                onChange={handleSliderChange("impact")}
                                tooltipVisible={false}
                            />
                        </div>
                    </div>

                    <div className="mb-3">
                        <label htmlFor="affected-props" className="text-dark">
                            Affected property(ies):
                        </label>
                        <Controller
                            name="affected_properties"
                            control={control}
                            rules={{ required: true }}
                            render={({ field: { onChange } }) =>
                                <Select className="react-select" classNamePrefix="react-select"
                                    options={risksAffectedPropertiesSelectOptions}
                                    closeMenuOnSelect={false}
                                    isMulti={true}
                                    defaultValue={data.affected_properties
                                        .split(",")
                                        .map((p) => ({ 
                                            label: p, value: p 
                                        }))}
                                    onChange={(values) => {
                                        onChange(values);
                                        handleAffectedPropertiesSelect(values);
                                    }
                                    
                                }
                                />
                                }
                            />
                            <div className="invalid-feedback d-block">
                                {error1.affected_properties ? <span>The Affected property(ies) field is required.</span> :
                                    <span> &nbsp; </span>}
                            </div>
                        {errors.affected_properties && (
                            <div className="invalid-feedback d-block">
                                <span>{errors.affected_properties}</span>
                            </div>
                        )}
                    </div>

                    <div className="mb-3">
                        <label
                            htmlFor="risk-treatment"
                            className="text-dark form-label"
                        >
                            Risk Treatment:
                        </label>
                        <Select
                            className="react-select"
                            classNamePrefix="react-select"
                            defaultValue={{ label: data.treatment_options, value: data.treatment_options }}
                            options={[
                                { label: 'Mitigate', value: 'Mitigate' },
                                { label: 'Accept', value: 'Accept' }
                            ]}
                            onChange={(value) =>
                                setData("treatment_options", value.value)
                            }
                        />
                    </div>

                    <div className="mb-3 position-relative">
                        <label
                            htmlFor="risk-treatment"
                            className="text-dark form-label"
                        >
                            Affected function/asset:
                        </label>
                        <Controller
                            name="affected_functions_or_assets"
                            control={control}
                            rules={{ required: true }}
                            render={({ field: {onChange} }) =>
                                <CustomCreatableSelect
                                    selectOptions={affectedFunctionsSelectOptions}
                                    selectValue={selected}
                                    onChangeHandler={(val) => {
                                        onChange(val);
                                        setSelected(val);
                                    }}
                                    onInputChangeHandler={setSelected}
                                />
                                }
                            />
                        <div className="invalid-feedback d-block">
                            {error1.affected_functions_or_assets ? <span>The Affected function/asset field is required.</span> :
                                <span> &nbsp; </span>}
                        </div>
                        {errors.affected_functions_or_assets && (
                            <div className="invalid-feedback d-block">
                                <span>
                                    {errors.affected_functions_or_assets}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                <div className="risk-score-container col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
                    {/* <!-- risk score section --> */}
                    <div className="risk-score">
                        <div className="riskscore mt-2">
                            <h4>
                                Inherent Risk Score:
                                <br />
                                <div className="riskscore-value">
                                    <span>{inherentScore}</span>
                                    <span
                                        className="risk-score-tag ms-2 font-xs"
                                        style={{
                                            color: inherentRiskScoreLevel.color,
                                        }}
                                    >
                                        {inherentRiskScoreLevel.name}
                                    </span>
                                </div>
                            </h4>
                        </div>

                        <div className="res-riskscore mt-3">
                            <h4>
                                Residual Risk Score:
                                <br />
                                <div className="riskscore-value">
                                    <span>{residualScore}</span>

                                    <span
                                        className="risk-score-tag ms-2 font-xs"
                                        style={{
                                            color: residualRiskScoreLevel.color,
                                        }}
                                    >
                                        {residualRiskScoreLevel.name}
                                    </span>
                                </div>
                            </h4>
                        </div>
                        {/* <!-- risk status --> */}
                        <div className="mt-3">
                            <h4>
                                Status:
                                {status === "Close" ? (
                                    <span
                                        className="risk-score-tag low ms-2 font-xs"
                                        id="risk_status"
                                    >
                                        Closed
                                    </span>
                                ) : (
                                    <span
                                        className="risk-score-tag extreme ms-2 font-xs"
                                        id="risk_status"
                                    >
                                        Open
                                    </span>
                                )}
                            </h4>
                        </div>
                    </div>
                </div>

                <div className="col-12">
                    <LoadingButton
                        className="btn btn-primary waves-effect waves-light"
                        clickable={clickable}
                        loading={processing}
                    >
                        Save
                    </LoadingButton>
                </div>
            </div>
            </form>
        </div>
    );
};

export default RiskItemDetails;
